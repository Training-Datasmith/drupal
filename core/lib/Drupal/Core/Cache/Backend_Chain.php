<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

/**
 * Defines a chained cache implementation for combining multiple cache backends.
 *
 * Can be used to combine two or more backends together to behave as if they
 * were a single backend.
 *
 * For example a slower, persistent storage engine could be combined with a
 * faster, volatile storage engine. When retrieving items from cache, they will
 * be fetched from the volatile backend first, only falling back to the
 * persistent backend if an item is not available. An item not present in the
 * volatile backend but found in the persistent one will be propagated back up
 * to ensure fast retrieval on the next request. On cache sets and deletes, both
 * backends will be invoked to ensure consistency.
 *
 * @see \Drupal\Core\Cache\ChainedFastBackend
 *
 * @ingroup cache
 */
class Backend_Chain implements Cache_Backend_Interface, Cache_Tags_Invalidator_Interface
{
    /**
     * Ordered list of CacheBackendInterface instances.
     *
     * @var array
     */
    protected $backends = [];
    /**
     * Appends a cache backend to the cache chain.
     *
     * @param CacheBackendInterface $backend
     *   The cache backend to be appended to the cache chain.
     *
     * @return $this
     *   The called object.
     */
    public function append_backend(Cache_Backend_Interface $backend): static
    {
        $this->backends[] = $backend;
        return $this;
    }
    /**
     * Prepends a cache backend to the cache chain.
     *
     * @param CacheBackendInterface $backend
     *   The backend to be prepended to the cache chain.
     *
     * @return $this
     *   The called object.
     */
    public function prepend_backend(Cache_Backend_Interface $backend): static
    {
        array_unshift($this->backends, $backend);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get($cid, $allow_invalid = false)
    {
        foreach ($this->backends as $index => $backend) {
            if (($return = $backend->get($cid, $allow_invalid)) !== false) {
                // We found a result, propagate it to all missed backends.
                if ($index > 0) {
                    for ($i = $index - 1; 0 <= $i; --$i) {
                        $this->backends[$i]->set($cid, $return->data, $return->expire, $return->tags);
                    }
                }
                return $return;
            }
        }
        return false;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_multiple(&$cids, $allow_invalid = false): array
    {
        $return = [];
        foreach ($this->backends as $index => $backend) {
            $items = $backend->get_multiple($cids, $allow_invalid);
            // Propagate the values that could be retrieved from the current cache
            // backend to all missed backends.
            if ($index > 0 && !empty($items)) {
                for ($i = $index - 1; 0 <= $i; --$i) {
                    foreach ($items as $cached) {
                        $this->backends[$i]->set($cached->cid, $cached->data, $cached->expire, $cached->tags);
                    }
                }
            }
            // Append the values to the previously retrieved ones.
            $return += $items;
            if (empty($cids)) {
                // No need to go further if we don't have any cid to fetch left.
                break;
            }
        }
        return $return;
    }
    /**
     * {@inheritdoc}
     */
    public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []): void
    {
        foreach ($this->backends as $backend) {
            $backend->set($cid, $data, $expire, $tags);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function set_multiple(array $items): void
    {
        foreach ($this->backends as $backend) {
            $backend->set_multiple($items);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function delete($cid): void
    {
        foreach ($this->backends as $backend) {
            $backend->delete($cid);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function delete_multiple(array $cids): void
    {
        foreach ($this->backends as $backend) {
            $backend->delete_multiple($cids);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function delete_all(): void
    {
        foreach ($this->backends as $backend) {
            $backend->delete_all();
        }
    }
    /**
     * {@inheritdoc}
     */
    public function invalidate($cid): void
    {
        foreach ($this->backends as $backend) {
            $backend->invalidate($cid);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function invalidate_multiple(array $cids): void
    {
        foreach ($this->backends as $backend) {
            $backend->invalidate_multiple($cids);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function invalidate_tags(array $tags): void
    {
        foreach ($this->backends as $backend) {
            if ($backend instanceof Cache_Tags_Invalidator_Interface) {
                $backend->invalidate_tags($tags);
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function garbage_collection(): void
    {
        foreach ($this->backends as $backend) {
            $backend->garbage_collection();
        }
    }
    /**
     * {@inheritdoc}
     */
    public function remove_bin(): void
    {
        foreach ($this->backends as $backend) {
            $backend->remove_bin();
        }
    }
}