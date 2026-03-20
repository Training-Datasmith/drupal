<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Memory_Cache;

use Drupal\Component\Datetime\Time_Interface;
use Drupal\Core\Cache\Cache;
/**
 * Defines a least recently used (LRU) static cache implementation.
 *
 * Stores cache items in memory using a PHP array. The number of cache items is
 * limited to a fixed number of slots. When the all slots are full, older items
 * are purged based on least recent usage.
 *
 * @ingroup cache
 */
class Lru_Memory_Cache extends Memory_Cache
{
    /**
     * Constructs an LruMemoryCache object.
     *
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     * @param int $allowedSlots
     *   The number of slots to allocate for items in the cache.
     */
    public function __construct(Time_Interface $time, protected readonly int $allowed_slots)
    {
        parent::__construct($time);
    }
    /**
     * {@inheritdoc}
     */
    public function get($cid, $allow_invalid = false)
    {
        if ($cached = parent::get($cid, $allow_invalid)) {
            $this->handle_cache_hits([$cid => $cached]);
        }
        return $cached;
    }
    /**
     * {@inheritdoc}
     */
    public function get_multiple(&$cids, $allow_invalid = false)
    {
        $ret = parent::get_multiple($cids, $allow_invalid);
        $this->handle_cache_hits($ret);
        return $ret;
    }
    /**
     * Moves an array of cache items to the most recently used positions.
     *
     * @param array $items
     *   An array of cache items keyed by cid.
     */
    private function handle_cache_hits(array $items): void
    {
        $last_key = array_key_last($this->cache);
        foreach ($items as $cid => $cached) {
            if ($cached->valid && $cid !== $last_key) {
                // Move valid items to the end of the array, so they will be removed
                // last.
                unset($this->cache[$cid]);
                $this->cache[$cid] = $cached;
                $last_key = $cid;
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []): void
    {
        if (isset($this->cache[$cid])) {
            // If the item is already in the cache, move it to end of the array.
            unset($this->cache[$cid]);
        } elseif (count($this->cache) > $this->allowed_slots - 1) {
            // Remove one item from the cache to ensure we remain within the allowed
            // number of slots. Avoid using array_slice() because it makes a copy of
            // the array, and avoid using array_splice() or array_shift() because they
            // re-index numeric keys.
            unset($this->cache[array_key_first($this->cache)]);
        }
        parent::set($cid, $data, $expire, $tags);
    }
    /**
     * {@inheritdoc}
     */
    public function invalidate($cid): void
    {
        $this->invalidate_multiple([$cid]);
    }
    /**
     * {@inheritdoc}
     */
    public function invalidate_multiple(array $cids): void
    {
        $items = [];
        foreach ($cids as $cid) {
            if (isset($this->cache[$cid])) {
                $items[$cid] = $this->cache[$cid];
                parent::invalidate($cid);
            }
        }
        $this->move_items_to_least_recently_used($items);
    }
    /**
     * {@inheritdoc}
     */
    public function invalidate_tags(array $tags): void
    {
        $items = [];
        foreach ($this->cache as $cid => $item) {
            if (array_intersect($tags, $item->tags)) {
                parent::invalidate($cid);
                $items[$cid] = $this->cache[$cid];
            }
        }
        $this->move_items_to_least_recently_used($items);
    }
    /**
     * Moves items to the least recently used positions.
     *
     * @param array $items
     *   An array of items to move to the least recently used positions.
     */
    private function move_items_to_least_recently_used(array $items): void
    {
        // This cannot use array_unshift() because it would reindex an array with
        // numeric cache IDs.
        if (!empty($items)) {
            $this->cache = $items + $this->cache;
        }
    }
}