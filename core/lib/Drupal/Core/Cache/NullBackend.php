<?php

declare (strict_types=1);
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
class Null_Backend implements Cache_Backend_Interface
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
    public function get_multiple(&$cids, $allow_invalid = false): array
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
    public function set_multiple(array $items = [])
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
    public function delete_multiple(array $cids)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function delete_all()
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
    public function invalidate_multiple(array $cids)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function garbage_collection()
    {
    }
    /**
     * {@inheritdoc}
     */
    public function remove_bin()
    {
    }
}