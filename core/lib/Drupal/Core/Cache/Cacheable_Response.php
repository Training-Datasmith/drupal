<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Symfony\Component\Http_Foundation\Response;
/**
 * A response that contains and can expose cacheability metadata.
 *
 * Supports Drupal's caching concepts: cache tags for invalidation and cache
 * contexts for variations.
 *
 * @see \Drupal\Core\Cache\Cache
 * @see \Drupal\Core\Cache\CacheableMetadata
 * @see \Drupal\Core\Cache\CacheableResponseTrait
 */
class Cacheable_Response extends Response implements Cacheable_Response_Interface
{
    use Cacheable_Response_Trait;
}