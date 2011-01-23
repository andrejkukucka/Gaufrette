<?php

namespace Gaufrette\Filesystem\Adapter;

use Gaufrette\Filesystem\Adapter;

/**
 * Adapter for the local filesystem
 *
 * @author Antoine Hérault <antoine.herault@gmail.com>
 */
class Local implements Adapter
{
    protected $directory;

    /**
     * Constructor
     *
     * @param  string  $directory Directory where the filesystem is located
     * @param  boolean $create    Whether to create the directory if it does not
     *                            exist (default FALSE)
     *
     * @throws RuntimeException if the specified directory does not exist and
     *                          could not be created
     */
    public function __construct($directory, $create = false)
    {
        $this->directory = $this->normalizePath($directory);
        $this->ensureDirectoryExists($this->directory, $create);
    }

    /**
     * Reads the content of the file
     *
     * @param  string $key
     */
    public function read($key)
    {
        return file_get_contents($this->computePath($key));
    }

    /**
     * Writes the given content into the file
     *
     * @param  string $key
     * @param  string $content
     *
     * @return integer Number of bytes that were written into the file, or
     *                 FALSE on failure
     */
    public function write($key, $content)
    {
        $path = $this->computePath($key);

        $this->ensureDirectoryExists(dirname($path), true);

        return file_put_contents($this->computePath($key), $content);
    }

    /**
     * Indicates whether the file exists
     *
     * @param  string $key
     *
     * @return boolean
     */
    public function exists($key)
    {
        return is_file($this->computePath($key));
    }

    /**
     * Don't forget the "/" if you want to list a specific directory
     *
     * @param  string $pattern
     *
     * @return array
     */
    public function keys($pattern)
    {
        $pattern = ltrim(str_replace('\\', '/', $pattern), '/');

        $pos = strrpos($pattern, '/');
        if (false === $pos) {
            return $this->listDirectory($this->computePath(null), $pattern);
        } elseif (strlen($pattern) === $pos + 1) {
            return $this->listDirectory($this->computePath(dirname($pattern)), null);
        } else {
            return $this->listDirectory($this->computePath(dirname($pattern)), basename($pattern));
        }
    }

    /**
     * Recursively lists files from the specified directory. If a pattern is
     * specified, it only returns files matching it.
     *
     * @param  string $directory The path of the directory to list files from
     * @param  string $pattern   The pattern that files must match to be
     *                           returned
     *
     * @return array An array of file keys
     */
    public function listDirectory($directory, $pattern = null)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        if (!empty($pattern)) {
            $iterator = new \RegexIterator(
                $iterator,
                sprintf('#^%s/%s#', $directory, $pattern),
                \RecursiveRegexIterator::MATCH
            );
        }

        $keys = array();
        foreach ($iterator as $item) {
            $item = strval($item);
            if (!is_dir($item)) {
                $keys[] = $this->computeKey($item);
            }
        }

        return $keys;
    }

    /**
     * Computes the path from the specified key
     *
     * @param  string $key The key which for to compute the path
     *
     * @return string A path
     *
     * @throws OutOfBoundsException If the computed path is out of the
     *                              directory
     */
    public function computePath($key)
    {
        $path = $this->normalizePath($this->directory . '/' . $key);

        if (0 !== strpos($path, $this->directory)) {
            throw new \OutOfBoundsException(sprintf('The file \'%s\' is out of the filesystem.', $key));
        }

        return $path;
    }

    /**
     * Normalizes the given path. It replaces backslashes by slashes, resolves
     * dots and removes double slashes
     *
     * @param  string $path The path to normalize
     *
     * @return string A normalized path
     *
     * @throws OutOfBoundsException If the given path is out of the directory
     */
    public function normalizePath($path)
    {
        // normalize directory separator and remove double slashes
        $path = trim(str_replace(array('\\', '//'), '/', $path), '/');

        // resolve dots
        $segments = explode('/', $path);
        $removed = array();
        foreach ($segments as $i => $segment) {
            if (in_array($segment, array('.', '..'))) {
                unset($segments[$i]);
                $removed[] = $i;
                if ('..' === $segment) {
                    $y = $i - 1;
                    while ($y > -1) {
                        if (!in_array($y, $removed) && !in_array($segments[$y], array('.', '..'))) {
                            unset($segments[$y]);
                            $removed[] = $y;
                            break;
                        }
                        $y--;
                    }
                }
            }
        }

        return '/' . implode('/', $segments);
    }

    /**
     * Computes the key from the specified path
     *
     * @param  string $path
     *
     * return string
     */
    public function computeKey($path)
    {
        if (0 !== strpos($path, $this->directory)) {
            throw new \OutOfBoundsException(sprintf('The path \'%s\' is out of the filesystem.', $path));
        }

        return ltrim(substr($path, strlen($this->directory)), '/');
    }

    /**
     * Ensures the specified directory exists, creates it if it does not
     *
     * @param  string  $directory Path of the directory to test
     * @param  boolean $create    Whether to create the directory if it does
     *                            not exist
     *
     * @throws RuntimeException if the directory does not exists and could not
     *                          be created
     */
    public function ensureDirectoryExists($directory, $create = false)
    {
        if (!is_dir($directory)) {
            if (!$create) {
                throw new \RuntimeException(sprintf('The directory \'%s\' does not exist.', $directory));
            }

            $this->createDirectory($directory);
        }
    }

    /**
     * Creates the specified directory and its parents
     *
     * @param  string $directory Path of the directory to create
     *
     * @throws InvalidArgumentException if the directory already exists
     * @throws RuntimeException         if the directory could not be created
     */
    public function createDirectory($directory)
    {
        if (is_dir($directory)) {
            throw new \InvalidArgumentException(sprintf('The directory \'%s\' already exists.', $directory));
        }

        $umask = umask(0);
        $created = mkdir($directory, 0777, true);
        umask($umask);

        if (!$created) {
            throw new \RuntimeException(sprintf('The directory \'%s\' could not be created.', $directory));
        }
    }
}
