<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Datetime\Time_Interface;
use Drupal\Component\Php_Storage\Php_Storage_Interface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Php_Storage\Php_Storage_Factory;
/**
 * Defines a PHP cache implementation.
 *
 * Stores cache items in a PHP file using a storage that implements
 * Drupal\Component\PhpStorage\PhpStorageInterface.
 *
 * This is fast because of PHP's opcode caching mechanism. Once a file's
 * content is stored in PHP's opcode cache, including it doesn't require
 * reading the contents from a filesystem. Instead, PHP will use the already
 * compiled opcodes stored in memory.
 *
 * @ingroup cache
 */
class Php_Backend implements Cache_Backend_Interface
{
    protected string $bin;
    /**
     * The PHP storage.
     */
    protected Php_Storage_Interface $storage;
    /**
     * Array to store cache objects.
     *
     * @var object[]
     */
    protected $cache = [];
    /**
     * Constructs a PhpBackend object.
     *
     * @param string $bin
     *   The cache bin for which the object is created.
     * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksumProvider
     *   The cache tags checksum provider.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(string $bin, protected \Drupal\Core\Cache\Cache_Tags_Checksum_Interface $checksum_provider, protected Time_Interface $time)
    {
        $this->bin = 'cache_' . $bin;
    }
    /**
     * {@inheritdoc}
     */
    public function get($cid, $allow_invalid = false)
    {
        return $this->get_by_hash($this->normalize_cid($cid), $allow_invalid);
    }
    /**
     * Fetch a cache item using a hashed cache ID.
     *
     * @param string $cidhash
     *   The hashed version of the original cache ID after being normalized.
     * @param bool $allow_invalid
     *   (optional) If TRUE, a cache item may be returned even if it is expired or
     *   has been invalidated.
     *
     * @return bool|mixed
     *   The requested cached item. Defaults to FALSE when the cache is not set.
     */
    protected function get_by_hash($cidhash, $allow_invalid = false)
    {
        if ($file = $this->storage()->get_full_path($cidhash)) {
            $cache = @include $file;
        }
        if (isset($cache)) {
            return $this->prepare_item($cache, $allow_invalid);
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function set_multiple(array $items): void
    {
        foreach ($items as $cid => $item) {
            $this->set($cid, $item['data'], $item['expire'] ?? Cache_Backend_Interface::CACHE_PERMANENT, $item['tags'] ?? []);
        }
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_multiple(&$cids, $allow_invalid = false): array
    {
        $ret = [];
        foreach ($cids as $cid) {
            if ($item = $this->get($cid, $allow_invalid)) {
                $ret[$item->cid] = $item;
            }
        }
        $cids = array_diff($cids, array_keys($ret));
        return $ret;
    }
    /**
     * Prepares a cached item.
     *
     * Checks that items are either permanent or did not expire, and returns data
     * as appropriate.
     *
     * @param object $cache
     *   An item loaded from self::get() or self::getMultiple().
     * @param bool $allow_invalid
     *   If FALSE, the method returns FALSE if the cache item is not valid.
     *
     * @return mixed
     *   The item with data as appropriate or FALSE if there is no
     *   valid item to load.
     */
    protected function prepare_item($cache, $allow_invalid): false|object
    {
        if (!isset($cache->data)) {
            return false;
        }
        // Check expire time.
        $cache->valid = $cache->expire == Cache::PERMANENT || $cache->expire >= $this->time->get_request_time();
        // Check if invalidateTags() has been called with any of the item's tags.
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
    public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []): void
    {
        assert(Inspector::assert_all_strings($tags), 'Cache Tags must be strings.');
        $item = (object) ['cid' => $cid, 'data' => $data, 'created' => round(microtime(true), 3), 'expire' => $expire, 'tags' => array_unique($tags), 'checksum' => $this->checksum_provider->get_current_checksum($tags)];
        $this->write_item($this->normalize_cid($cid), $item);
    }
    /**
     * {@inheritdoc}
     */
    public function delete($cid): void
    {
        $this->storage()->delete($this->normalize_cid($cid));
    }
    /**
     * {@inheritdoc}
     */
    public function delete_multiple(array $cids): void
    {
        foreach ($cids as $cid) {
            $this->delete($cid);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function delete_all(): void
    {
        $this->storage()->delete_all();
    }
    /**
     * {@inheritdoc}
     */
    public function invalidate($cid): void
    {
        $this->invalidate_by_hash($this->normalize_cid($cid));
    }
    /**
     * Invalidate one cache item.
     *
     * @param string $cidhash
     *   The hashed version of the original cache ID after being normalized.
     */
    protected function invalidate_by_hash($cidhash)
    {
        if ($item = $this->get_by_hash($cidhash)) {
            $item->expire = $this->time->get_request_time() - 1;
            $this->write_item($cidhash, $item);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function invalidate_multiple(array $cids): void
    {
        foreach ($cids as $cid) {
            $this->invalidate($cid);
        }
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
    public function remove_bin(): void
    {
        $this->cache = [];
        $this->storage()->delete_all();
    }
    /**
     * Writes a cache item to PhpStorage.
     *
     * @param string $cidhash
     *   The hashed version of the original cache ID after being normalized.
     * @param object $item
     *   The cache item to store.
     */
    protected function write_item($cidhash, \stdClass $item)
    {
        $content = '<?php return unserialize(' . var_export(serialize($item), true) . ');';
        $this->storage()->save($cidhash, $content);
    }
    /**
     * Gets the PHP code storage object to use.
     *
     * @return \Drupal\Component\PhpStorage\PhpStorageInterface
     *   The PHP storage.
     */
    protected function storage()
    {
        if (!isset($this->storage)) {
            $this->storage = Php_Storage_Factory::get($this->bin);
        }
        return $this->storage;
    }
    /**
     * Ensures a normalized cache ID.
     *
     * @param string $cid
     *   The passed in cache ID.
     *
     * @return string
     *   A normalized cache ID.
     */
    protected function normalize_cid($cid): string
    {
        return Crypt::hash_base64($cid);
    }
}