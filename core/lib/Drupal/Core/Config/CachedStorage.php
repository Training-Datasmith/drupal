<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Core\Dependency_Injection\Dependency_Serialization_Trait;
/**
 * Defines the cached storage.
 *
 * The class gets another storage and a cache backend injected. It reads from
 * the cache and delegates the read to the storage on a cache miss. It also
 * handles cache invalidation.
 */
class Cached_Storage implements Storage_Interface, Storage_Cache_Interface
{
    use Dependency_Serialization_Trait;
    /**
     * List of listAll() prefixes with their results.
     *
     * @var array
     */
    protected $find_by_prefix_cache = [];
    /**
     * Constructs a new CachedStorage.
     *
     * @param \Drupal\Core\Config\StorageInterface $storage
     *   A configuration storage to be cached.
     * @param \Drupal\Core\Cache\CacheBackendInterface $cache
     *   A cache backend used to store configuration.
     */
    public function __construct(protected \Drupal\Core\Config\Storage_Interface $storage, protected \Drupal\Core\Cache\Cache_Backend_Interface $cache)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        // The cache would read in the entire data (instead of only checking whether
        // any data exists), and on a potential cache miss, an additional storage
        // lookup would have to happen, so check the storage directly.
        return $this->storage->exists($name);
    }
    /**
     * {@inheritdoc}
     */
    public function read($name)
    {
        $cache_key = $this->get_cache_key($name);
        if ($cache = $this->cache->get($cache_key)) {
            // The cache contains either the cached configuration data or FALSE
            // if the configuration file does not exist.
            return $cache->data;
        }
        // Read from the storage on a cache miss and cache the data. Also cache
        // information about missing configuration objects.
        $data = $this->storage->read($name);
        $this->cache->set($cache_key, $data);
        return $data;
    }
    /**
     * {@inheritdoc}
     */
    public function read_multiple(array $names): array
    {
        $data_to_return = [];
        $cache_keys_map = $this->get_cache_keys($names);
        $cache_keys = array_values($cache_keys_map);
        $cached_list = $this->cache->get_multiple($cache_keys);
        if (!empty($cache_keys)) {
            // $cache_keys_map contains the full $name => $cache_key map, while
            // $cache_keys contains just the $cache_key values that weren't found in
            // the cache.
            // @see \Drupal\Core\Cache\CacheBackendInterface::getMultiple()
            $names_to_get = array_keys(array_intersect($cache_keys_map, $cache_keys));
            $list = $this->storage->read_multiple($names_to_get);
            // Cache configuration objects that were loaded from the storage, cache
            // missing configuration objects as an explicit FALSE.
            $items = [];
            foreach ($names_to_get as $name) {
                $data = $list[$name] ?? false;
                $data_to_return[$name] = $data;
                $items[$cache_keys_map[$name]] = ['data' => $data];
            }
            $this->cache->set_multiple($items);
        }
        // Add the configuration objects from the cache to the list.
        $cache_keys_inverse_map = array_flip($cache_keys_map);
        foreach ($cached_list as $cache_key => $cache) {
            $name = $cache_keys_inverse_map[$cache_key];
            $data_to_return[$name] = $cache->data;
        }
        // Ensure that only existing configuration objects are returned, filter out
        // cached information about missing objects.
        return array_filter($data_to_return);
    }
    /**
     * {@inheritdoc}
     */
    public function write($name, array $data): bool
    {
        if ($this->storage->write($name, $data)) {
            // While not all written data is read back, setting the cache instead of
            // just deleting it avoids cache rebuild stampedes.
            $this->cache->set($this->get_cache_key($name), $data);
            $this->find_by_prefix_cache = [];
            return true;
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function delete($name): bool
    {
        // If the cache was the first to be deleted, another process might start
        // rebuilding the cache before the storage is gone.
        if ($this->storage->delete($name)) {
            $this->cache->delete($this->get_cache_key($name));
            $this->find_by_prefix_cache = [];
            return true;
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function rename($name, $new_name): bool
    {
        // If the cache was the first to be deleted, another process might start
        // rebuilding the cache before the storage is renamed.
        if ($this->storage->rename($name, $new_name)) {
            $this->cache->delete($this->get_cache_key($name));
            $this->cache->delete($this->get_cache_key($new_name));
            $this->find_by_prefix_cache = [];
            return true;
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        return $this->storage->encode($data);
    }
    /**
     * {@inheritdoc}
     */
    public function decode($raw)
    {
        return $this->storage->decode($raw);
    }
    /**
     * {@inheritdoc}
     */
    public function list_all($prefix = '')
    {
        // Do not cache when a prefix is not provided.
        if ($prefix) {
            return $this->find_by_prefix($prefix);
        }
        return $this->storage->list_all();
    }
    /**
     * Finds configuration object names starting with a given prefix.
     *
     * Given the following configuration objects:
     * - node.type.article
     * - node.type.page
     *
     * Passing the prefix 'node.type.' will return an array containing the above
     * names.
     *
     * @param string $prefix
     *   The prefix to search for.
     *
     * @return array
     *   An array containing matching configuration object names.
     */
    protected function find_by_prefix($prefix)
    {
        $cache_key = $this->get_cache_key($prefix);
        if (!isset($this->find_by_prefix_cache[$cache_key])) {
            $this->find_by_prefix_cache[$cache_key] = $this->storage->list_all($prefix);
        }
        return $this->find_by_prefix_cache[$cache_key];
    }
    /**
     * {@inheritdoc}
     */
    public function delete_all($prefix = ''): bool
    {
        // If the cache was the first to be deleted, another process might start
        // rebuilding the cache before the storage is renamed.
        $names = $this->storage->list_all($prefix);
        if ($this->storage->delete_all($prefix)) {
            $this->cache->delete_multiple($this->get_cache_keys($names));
            return true;
        }
        return false;
    }
    /**
     * Clears the static list cache.
     */
    public function reset_list_cache(): void
    {
        $this->find_by_prefix_cache = [];
    }
    /**
     * {@inheritdoc}
     */
    public function create_collection($collection): static
    {
        return new static($this->storage->create_collection($collection), $this->cache);
    }
    /**
     * {@inheritdoc}
     */
    public function get_all_collection_names()
    {
        return $this->storage->get_all_collection_names();
    }
    /**
     * {@inheritdoc}
     */
    public function get_collection_name()
    {
        return $this->storage->get_collection_name();
    }
    /**
     * Returns a cache key for a configuration name using the collection.
     *
     * @param string $name
     *   The configuration name.
     *
     * @return string
     *   The cache key for the configuration name.
     */
    protected function get_cache_key(string $name): string
    {
        return $this->get_collection_prefix() . $name;
    }
    /**
     * Returns a cache key map for an array of configuration names.
     *
     * @param array $names
     *   The configuration names.
     *
     * @return array
     *   An array of cache keys keyed by configuration names.
     */
    protected function get_cache_keys(array $names): array
    {
        $prefix = $this->get_collection_prefix();
        $cache_keys = array_map(fn($name) => $prefix . $name, $names);
        return array_combine($names, $cache_keys);
    }
    /**
     * Returns a cache ID prefix to use for the collection.
     *
     * @return string
     *   The cache ID prefix.
     */
    protected function get_collection_prefix(): string
    {
        $collection = $this->storage->get_collection_name();
        if ($collection == Storage_Interface::DEFAULT_COLLECTION) {
            return '';
        }
        return $collection . ':';
    }
}