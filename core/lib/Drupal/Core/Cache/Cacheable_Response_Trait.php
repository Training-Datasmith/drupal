<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

/**
 * Provides an implementation of CacheableResponseInterface.
 *
 * @see \Drupal\Core\Cache\CacheableResponseInterface
 */
trait Cacheable_Response_Trait
{
    /**
     * The cacheability metadata.
     *
     * @var \Drupal\Core\Cache\CacheableMetadata
     */
    protected $cacheability_metadata;
    /**
     * {@inheritdoc}
     */
    public function add_cacheable_dependency($dependency)
    {
        // A trait doesn't have a constructor, so initialize the cacheability
        // metadata if that hasn't happened yet.
        if (!isset($this->cacheability_metadata)) {
            $this->cacheability_metadata = new Cacheable_Metadata();
        }
        $this->cacheability_metadata = $this->cacheability_metadata->merge(Cacheable_Metadata::create_from_object($dependency));
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata()
    {
        // A trait doesn't have a constructor, so initialize the cacheability
        // metadata if that hasn't happened yet.
        if (!isset($this->cacheability_metadata)) {
            $this->cacheability_metadata = new Cacheable_Metadata();
        }
        return $this->cacheability_metadata;
    }
}