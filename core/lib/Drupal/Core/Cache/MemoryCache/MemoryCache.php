<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Memory_Cache;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Cache\Memory_Backend;
/**
 * Defines a memory cache implementation.
 *
 * Stores cache items in memory using a PHP array.
 *
 * @ingroup cache
 */
class Memory_Cache extends Memory_Backend implements Memory_Cache_Interface
{
    /**
     * Prepares a cached item.
     *
     * Checks that items are either permanent or did not expire, and returns data
     * as appropriate.
     *
     * @param object $cache
     *   An item loaded from self::get() or self::getMultiple().
     * @param bool $allow_invalid
     *   (optional) If TRUE, cache items may be returned even if they have expired
     *   or been invalidated. Defaults to FALSE.
     *
     * @return mixed
     *   The item with data as appropriate or FALSE if there is no
     *   valid item to load.
     */
    protected function prepare_item($cache, $allow_invalid = false): false|object
    {
        if (!isset($cache->data)) {
            return false;
        }
        // Check expire time.
        $cache->valid = $cache->expire == static::CACHE_PERMANENT || $cache->expire >= $this->time->get_request_time();
        if (!$allow_invalid && !$cache->valid) {
            return false;
        }
        return $cache;
    }
    /**
     * {@inheritdoc}
     */
    public function set($cid, $data, $expire = Memory_Cache_Interface::CACHE_PERMANENT, array $tags = []): void
    {
        assert(Inspector::assert_all_strings($tags), 'Cache tags must be strings.');
        $tags = array_unique($tags);
        $this->cache[$cid] = (object) ['cid' => $cid, 'data' => $data, 'created' => $this->time->get_request_time(), 'expire' => $expire, 'tags' => $tags];
    }
}