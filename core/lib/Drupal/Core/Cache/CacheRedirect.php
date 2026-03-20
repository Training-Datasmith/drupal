<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

/**
 * Defines a value object to represent a cache redirect.
 *
 * @see \Drupal\Core\Cache\VariationCache::get()
 * @see \Drupal\Core\Cache\VariationCache::set()
 *
 * @ingroup cache
 * @internal
 */
class Cache_Redirect implements Cacheable_Dependency_Interface
{
    use Cacheable_Dependency_Trait;
    /**
     * Constructs a CacheRedirect object.
     *
     * @param \Drupal\Core\Cache\CacheableDependencyInterface $cacheability
     *   The cacheability to redirect to.
     *
     * @see \Drupal\Core\Cache\VariationCache::createCacheIdFast()
     */
    public function __construct(Cacheable_Dependency_Interface $cacheability)
    {
        // Cache redirects only care about cache contexts.
        $this->cache_contexts = $cacheability->get_cache_contexts();
    }
}