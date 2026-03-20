<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Datetime\Time_Interface;
/**
 * Stores cache items in the Alternative PHP Cache User Cache (APCu).
 */
class Apcu_Backend implements Cache_Backend_Interface
{
    /**
     * Prefix for all keys in this cache bin.
     *
     * Includes the site-specific prefix in $sitePrefix.
     */
    protected string $bin_prefix;
    /**
     * Constructs a new ApcuBackend instance.
     *
     * @param string $bin
     *   The name of the cache bin.
     * @param string $sitePrefix
     *   The prefix to use for all keys in the storage that belong to this site.
     * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksumProvider
     *   The cache tags checksum provider.
     * @param \Drupal\Component\Datetime\TimeInterface|null $time
     *   The time service.
     */
    public function __construct(
        /**
         * The name of the cache bin to use.
         */
        protected $bin,
        /**
         * Prefix for all keys in the storage that belong to this site.
         */
        protected $site_prefix,
        protected \Drupal\Core\Cache\Cache_Tags_Checksum_Interface $checksum_provider,
        protected Time_Interface $time
    )
    {
        $this->bin_prefix = $this->site_prefix . '::' . $this->bin . '::';
    }
    /**
     * Prepends the APCu user variable prefix for this bin to a cache item ID.
     *
     * @param string $cid
     *   The cache item ID to prefix.
     *
     * @return string
     *   The APCu key for the cache item ID.
     */
    public function get_apcu_key(string $cid): string
    {
        return $this->bin_prefix . $cid;
    }
    /**
     * {@inheritdoc}
     */
    public function get($cid, $allow_invalid = false)
    {
        $cache = apcu_fetch($this->get_apcu_key($cid));
        return $this->prepare_item($cache, $allow_invalid);
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_multiple(&$cids, $allow_invalid = false): array
    {
        // Translate the requested cache item IDs to APCu keys.
        $map = [];
        foreach ($cids as $cid) {
            $map[$this->get_apcu_key($cid)] = $cid;
        }
        $result = apcu_fetch(array_keys($map));
        $cache = [];
        if ($result) {
            // Before checking the validity of each item individually, register the
            // cache tags for all returned cache items for preloading, this allows the
            // cache tag service to optimize cache tag lookups.
            if ($this->checksum_provider instanceof Cache_Tags_Checksum_Preload_Interface) {
                $tags_for_preload = [];
                foreach ($result as $item) {
                    if ($item->tags) {
                        $tags_for_preload[] = explode(' ', (string) $item->tags);
                    }
                }
                $this->checksum_provider->register_cache_tags_for_preload(array_merge(...$tags_for_preload));
            }
            foreach ($result as $key => $item) {
                $item = $this->prepare_item($item, $allow_invalid);
                if ($item) {
                    $cache[$map[$key]] = $item;
                }
            }
        }
        unset($result);
        $cids = array_diff($cids, array_keys($cache));
        return $cache;
    }
    /**
     * Returns all cached items, optionally limited by a cache ID prefix.
     *
     * APCu is a memory cache, shared across all server processes. To prevent
     * cache item clashes with other applications/installations, every cache item
     * is prefixed with a unique string for this site. Therefore, functions like
     * apcu_clear_cache() cannot be used, and instead, a list of all cache items
     * belonging to this application need to be retrieved through this method
     * instead.
     *
     * @param string $prefix
     *   (optional) A cache ID prefix to limit the result to.
     *
     * @return \APCUIterator
     *   An APCUIterator containing matched items.
     */
    protected function get_all($prefix = '')
    {
        return $this->getIterator('/^' . preg_quote($this->get_apcu_key($prefix), '/') . '/');
    }
    /**
     * Prepares a cached item.
     *
     * Checks that the item is either permanent or did not expire.
     *
     * @param object $cache
     *   An item loaded from self::get() or self::getMultiple().
     * @param bool $allow_invalid
     *   If TRUE, a cache item may be returned even if it is expired or has been
     *   invalidated. See ::get().
     *
     * @return mixed
     *   The cache item or FALSE if the item expired.
     */
    protected function prepare_item($cache, $allow_invalid): false|object
    {
        if (!isset($cache->data)) {
            return false;
        }
        $cache->tags = $cache->tags ? explode(' ', $cache->tags) : [];
        // Check expire time.
        $cache->valid = $cache->expire == Cache::PERMANENT || $cache->expire >= $this->time->get_request_time();
        // Check if invalidateTags() has been called with any of the entry's tags.
        if (!$this->checksum_provider->is_valid($cache->checksum, $cache->tags)) {
            $cache->valid = false;
        }
        if (!$allow_invalid && !$cache->valid) {
            return false;
        }
        return $cache;
    }
    /**
     * {@inheritdoc}
     */
    public function set($cid, $data, $expire = Cache_Backend_Interface::CACHE_PERMANENT, array $tags = []): void
    {
        assert(Inspector::assert_all_strings($tags), 'Cache tags must be strings.');
        $tags = array_unique($tags);
        $cache = new \stdClass();
        $cache->cid = $cid;
        $cache->created = round(microtime(true), 3);
        $cache->expire = $expire;
        $cache->tags = implode(' ', $tags);
        $cache->checksum = $this->checksum_provider->get_current_checksum($tags);
        // APCu serializes/unserializes any structure itself.
        $cache->serialized = 0;
        $cache->data = $data;
        // Expiration is handled by our own prepareItem(), not APCu.
        apcu_store($this->get_apcu_key($cid), $cache);
    }
    /**
     * {@inheritdoc}
     */
    public function set_multiple(array $items = []): void
    {
        foreach ($items as $cid => $item) {
            $this->set($cid, $item['data'], $item['expire'] ?? Cache_Backend_Interface::CACHE_PERMANENT, $item['tags'] ?? []);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function delete($cid): void
    {
        apcu_delete($this->get_apcu_key($cid));
    }
    /**
     * {@inheritdoc}
     */
    public function delete_multiple(array $cids): void
    {
        apcu_delete(array_map($this->get_apcu_key(...), $cids));
    }
    /**
     * {@inheritdoc}
     */
    public function delete_all(): void
    {
        apcu_delete($this->getIterator('/^' . preg_quote($this->bin_prefix, '/') . '/'));
    }
    /**
     * {@inheritdoc}
     */
    public function garbage_collection(): void
    {
        // APCu performs garbage collection automatically.
    }
    /**
     * {@inheritdoc}
     */
    public function remove_bin(): void
    {
        apcu_delete($this->getIterator('/^' . preg_quote($this->bin_prefix, '/') . '/'));
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
        foreach ($this->get_multiple($cids) as $cache) {
            $this->set($cache->cid, $cache, $this->time->get_request_time() - 1);
        }
    }
    /**
     * Instantiates and returns the APCUIterator class.
     *
     * @param mixed $search
     *   A PCRE regular expression that matches against APC key names, either as a
     *   string for a single regular expression, or as an array of regular
     *   expressions. Or, optionally pass in NULL to skip the search.
     * @param int $format
     *   The desired format, as configured with one or more of the APC_ITER_*
     *   constants.
     * @param int $chunk_size
     *   The chunk size. Must be a value greater than 0. The default value is 100.
     * @param int $list
     *   The type to list. Either pass in APC_LIST_ACTIVE or APC_LIST_DELETED.
     *
     * @return \APCUIterator
     *   An APCUIterator class.
     */
    protected function getIterator($search = null, $format = APC_ITER_ALL, $chunk_size = 100, $list = APC_LIST_ACTIVE): \Apcu_Iterator
    {
        return new \Apcu_Iterator($search, $format, $chunk_size, $list);
    }
}