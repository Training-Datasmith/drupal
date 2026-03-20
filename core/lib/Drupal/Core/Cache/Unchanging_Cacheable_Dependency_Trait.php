<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

/**
 * Trait to implement CacheableDependencyInterface for unchanging objects.
 *
 * @see \Drupal\Core\Cache\CacheableDependencyInterface
 */
trait Unchanging_Cacheable_Dependency_Trait
{
    /**
     * {@inheritdoc}
     */
    public function get_cache_contexts(): array
    {
        return [];
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_tags(): array
    {
        return [];
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_max_age(): int
    {
        return Cache::PERMANENT;
    }
}