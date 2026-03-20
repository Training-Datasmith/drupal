<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Drupal\Component\Assertion\Inspector;
/**
 * Passes cache tag events to classes that wish to respond to them.
 */
class Cache_Tags_Invalidator implements Cache_Tags_Invalidator_Interface, Cache_Tags_Purge_Interface
{
    /**
     * Holds an array of cache tags invalidators.
     *
     * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface[]
     */
    protected $invalidators = [];
    /**
     * Holds an array of cache bins that support invalidations.
     *
     * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface[]
     */
    protected array $bins = [];
    /**
     * {@inheritdoc}
     */
    public function invalidate_tags(array $tags): void
    {
        assert(Inspector::assert_all_strings($tags), 'Cache tags must be strings.');
        // Notify all added cache tags invalidators.
        foreach ($this->invalidators as $invalidator) {
            $invalidator->invalidate_tags($tags);
        }
        // Additionally, notify each cache bin if it implements the service.
        foreach ($this->bins as $bin) {
            $bin->invalidate_tags($tags);
        }
    }
    /**
     * Reset statically cached tags in all cache tag checksum services.
     *
     * This is only used by tests.
     */
    public function reset_checksums(): void
    {
        foreach ($this->invalidators as $invalidator) {
            if ($invalidator instanceof Cache_Tags_Checksum_Interface) {
                $invalidator->reset();
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function purge(): void
    {
        foreach ($this->invalidators as $invalidator) {
            if ($invalidator instanceof Cache_Tags_Purge_Interface) {
                $invalidator->purge();
            }
        }
    }
    /**
     * Adds a cache tags invalidator.
     *
     * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $invalidator
     *   A cache invalidator.
     */
    public function add_invalidator(Cache_Tags_Invalidator_Interface $invalidator): void
    {
        $this->invalidators[] = $invalidator;
    }
    /**
     * Adds a cache bin.
     *
     * @param \Drupal\Core\Cache\CacheBackendInterface $bin
     *   A cache bin.
     */
    public function add_bin(Cache_Backend_Interface $bin): void
    {
        if ($bin instanceof Cache_Tags_Invalidator_Interface) {
            $this->bins[] = $bin;
        }
    }
}