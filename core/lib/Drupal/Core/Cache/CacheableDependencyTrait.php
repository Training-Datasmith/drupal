<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

/**
 * Trait for \Drupal\Core\Cache\CacheableDependencyInterface.
 */
trait Cacheable_Dependency_Trait
{
    /**
     * Cache contexts.
     *
     * @var string[]
     */
    protected $cache_contexts = [];
    /**
     * Cache tags.
     *
     * @var list<string>
     */
    protected $cache_tags = [];
    /**
     * Cache max-age.
     *
     * @var int
     */
    protected $cache_max_age = Cache::PERMANENT;
    /**
     * Sets cacheability; useful for value object constructors.
     *
     * @param \Drupal\Core\Cache\CacheableDependencyInterface $cacheability
     *   The cacheability to set.
     *
     * @return $this
     */
    protected function set_cacheability(Cacheable_Dependency_Interface $cacheability)
    {
        $this->cache_contexts = $cacheability->get_cache_contexts();
        $this->cache_tags = $cacheability->get_cache_tags();
        $this->cache_max_age = $cacheability->get_cache_max_age();
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_tags()
    {
        return $this->cache_tags;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_contexts()
    {
        return $this->cache_contexts;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_max_age()
    {
        return $this->cache_max_age;
    }
}