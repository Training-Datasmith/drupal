<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

/**
 * Defines a generic class for passing cacheability metadata.
 *
 * @ingroup cache
 */
class Cacheable_Metadata implements Refinable_Cacheable_Dependency_Interface
{
    use Refinable_Cacheable_Dependency_Trait;
    /**
     * {@inheritdoc}
     */
    public function get_cache_tags()
    {
        return $this->cache_tags;
    }
    /**
     * Sets cache tags.
     *
     * @param string[] $cache_tags
     *   The cache tags to be associated.
     *
     * @return $this
     */
    public function set_cache_tags(array $cache_tags): static
    {
        $this->cache_tags = $cache_tags;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_contexts()
    {
        return $this->cache_contexts;
    }
    /**
     * Sets cache contexts.
     *
     * @param string[] $cache_contexts
     *   The cache contexts to be associated.
     *
     * @return $this
     */
    public function set_cache_contexts(array $cache_contexts): static
    {
        $this->cache_contexts = $cache_contexts;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_max_age()
    {
        return $this->cache_max_age;
    }
    /**
     * Sets the maximum age (in seconds).
     *
     * Defaults to Cache::PERMANENT
     *
     * @param int $max_age
     *   The max age to associate.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     *   If a non-integer value is supplied.
     */
    public function set_cache_max_age($max_age): static
    {
        if (!is_int($max_age)) {
            throw new \InvalidArgumentException('$max_age must be an integer');
        }
        $this->cache_max_age = $max_age;
        return $this;
    }
    /**
     * Merges the values of another CacheableMetadata object with this one.
     *
     * @param \Drupal\Core\Cache\CacheableMetadata $other
     *   The other CacheableMetadata object.
     *
     * @return static
     *   A new CacheableMetadata object, with the merged data.
     */
    public function merge(Cacheable_Metadata $other): static
    {
        $result = clone $this;
        // This is called many times per request, so avoid merging unless absolutely
        // necessary.
        if (empty($this->cache_contexts)) {
            $result->cache_contexts = $other->cache_contexts;
        } elseif (empty($other->cache_contexts)) {
            $result->cache_contexts = $this->cache_contexts;
        } else {
            $result->cache_contexts = Cache::merge_contexts($this->cache_contexts, $other->cache_contexts);
        }
        if (empty($this->cache_tags)) {
            $result->cache_tags = $other->cache_tags;
        } elseif (empty($other->cache_tags)) {
            $result->cache_tags = $this->cache_tags;
        } else {
            $result->cache_tags = Cache::merge_tags($this->cache_tags, $other->cache_tags);
        }
        if ($this->cache_max_age === Cache::PERMANENT) {
            $result->cache_max_age = $other->cache_max_age;
        } elseif ($other->cache_max_age === Cache::PERMANENT) {
            $result->cache_max_age = $this->cache_max_age;
        } else {
            $result->cache_max_age = Cache::merge_max_ages($this->cache_max_age, $other->cache_max_age);
        }
        return $result;
    }
    /**
     * Applies the values of this CacheableMetadata object to a render array.
     *
     * @param array &$build
     *   A render array.
     */
    public function apply_to(array &$build): void
    {
        $build['#cache']['contexts'] = $this->cache_contexts;
        $build['#cache']['tags'] = $this->cache_tags;
        $build['#cache']['max-age'] = $this->cache_max_age;
    }
    /**
     * Creates a CacheableMetadata object with values taken from a render array.
     *
     * @param array $build
     *   A render array.
     */
    public static function create_from_render_array(array $build): static
    {
        $meta = new static();
        $meta->cache_contexts = $build['#cache']['contexts'] ?? [];
        $meta->cache_tags = $build['#cache']['tags'] ?? [];
        $meta->cache_max_age = $build['#cache']['max-age'] ?? Cache::PERMANENT;
        return $meta;
    }
    /**
     * Creates a CacheableMetadata object from a depended object.
     *
     * @param \Drupal\Core\Cache\CacheableDependencyInterface|mixed $object
     *   The object whose cacheability metadata to retrieve. If it implements
     *   CacheableDependencyInterface, its cacheability metadata will be used,
     *   otherwise, the passed in object must be assumed to be uncacheable, so
     *   max-age 0 is set.
     */
    public static function create_from_object($object): static
    {
        if ($object instanceof Cacheable_Dependency_Interface) {
            $meta = new static();
            $meta->cache_contexts = $object->get_cache_contexts();
            $meta->cache_tags = $object->get_cache_tags();
            $meta->cache_max_age = $object->get_cache_max_age();
            return $meta;
        }
        // Objects that don't implement CacheableDependencyInterface must be assumed
        // to be uncacheable, so set max-age 0.
        $meta = new static();
        $meta->cache_max_age = 0;
        return $meta;
    }
}