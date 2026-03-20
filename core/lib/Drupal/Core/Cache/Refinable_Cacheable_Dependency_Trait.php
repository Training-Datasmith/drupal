<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

/**
 * Trait for \Drupal\Core\Cache\RefinableCacheableDependencyInterface.
 */
trait Refinable_Cacheable_Dependency_Trait
{
    use Cacheable_Dependency_Trait;
    /**
     * {@inheritdoc}
     */
    public function add_cacheable_dependency($other_object)
    {
        $this->add_cache_contexts($other_object->get_cache_contexts());
        $this->add_cache_tags($other_object->get_cache_tags());
        $this->merge_cache_max_age($other_object->get_cache_max_age());
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function add_cache_contexts(array $cache_contexts)
    {
        if ($cache_contexts) {
            $this->cache_contexts = Cache::merge_contexts($this->cache_contexts, $cache_contexts);
        }
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function add_cache_tags(array $cache_tags)
    {
        if ($cache_tags) {
            $this->cache_tags = Cache::merge_tags($this->cache_tags, $cache_tags);
        }
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function merge_cache_max_age($max_age)
    {
        $this->cache_max_age = Cache::merge_max_ages($this->cache_max_age, $max_age);
        return $this;
    }
}