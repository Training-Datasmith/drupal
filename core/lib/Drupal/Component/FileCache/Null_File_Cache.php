<?php

declare (strict_types=1);
namespace Drupal\Component\File_Cache;

/**
 * Null implementation for the file cache.
 */
class Null_File_Cache implements File_Cache_Interface
{
    /**
     * {@inheritdoc}
     */
    public function get($filepath): null
    {
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function get_multiple(array $filepaths): array
    {
        return [];
    }
    /**
     * {@inheritdoc}
     */
    public function set($filepath, $data)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function delete($filepath)
    {
    }
}