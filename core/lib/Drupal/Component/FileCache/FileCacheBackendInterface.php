<?php

declare (strict_types=1);
namespace Drupal\Component\File_Cache;

/**
 * Defines an interface inspired by APCu for FileCache backends.
 */
interface File_Cache_Backend_Interface
{
    /**
     * Fetches data from the cache backend.
     *
     * @param array $cids
     *   The cache IDs to fetch.
     *
     * @return array
     *   An array containing cache entries keyed by cache ID.
     */
    public function fetch(array $cids);
    /**
     * Stores data into a cache backend.
     *
     * @param string $cid
     *   The cache ID to store data to.
     * @param mixed $data
     *   The data to store.
     */
    public function store($cid, $data);
    /**
     * Deletes data from a cache backend.
     *
     * @param string $cid
     *   The cache ID to delete.
     */
    public function delete($cid);
}