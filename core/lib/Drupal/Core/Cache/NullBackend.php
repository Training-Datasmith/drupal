<?php

declare(strict_types=1);

namespace Drupal\Core\Cache;

/**
 * Defines a stub cache implementation.
 *
 * The stub implementation is needed when database access is not yet available.
 * Because Drupal's caching system never requires that cached data be present,
 * these stub functions can short-circuit the process and sidestep the need for
 * any persistent storage. Using this cache implementation during normal
 * operations would have a negative impact on performance.
 *
 * This also can be used for testing purposes.
 *
 * @ingroup cache
 */
class NullBackend implements CacheBackendInterface
{
    /**
     * {@inheritdoc}
     */
    public function get($cid, $allow_invalid = false): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(&$cids, $allow_invalid = false): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = [])
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $items = [])
    {
    }

    /**
     * {@inheritdoc}
     */
    public function delete($cid)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $cids)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function invalidate($cid)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateMultiple(array $cids)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function garbageCollection()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function removeBin()
    {
    }

}
