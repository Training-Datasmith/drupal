<?php

declare (strict_types=1);
namespace Drupal\Component\File_Cache;

/**
 * APCu backend for the file cache.
 */
class Apcu_File_Cache_Backend implements File_Cache_Backend_Interface
{
    /**
     * {@inheritdoc}
     */
    public function fetch(array $cids): mixed
    {
        return apcu_fetch($cids);
    }
    /**
     * {@inheritdoc}
     */
    public function store($cid, $data): void
    {
        apcu_store($cid, $data);
    }
    /**
     * {@inheritdoc}
     */
    public function delete($cid): void
    {
        apcu_delete($cid);
    }
}