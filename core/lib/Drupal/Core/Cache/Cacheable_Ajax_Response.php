<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Drupal\Core\Ajax\Ajax_Response;
/**
 * A AjaxResponse that contains and can expose cacheability metadata.
 *
 * Supports Drupal's caching concepts: cache tags for invalidation and cache
 * contexts for variations.
 *
 * @see \Drupal\Core\Cache\Cache
 * @see \Drupal\Core\Cache\CacheableMetadata
 * @see \Drupal\Core\Cache\CacheableResponseTrait
 */
class Cacheable_Ajax_Response extends Ajax_Response implements Cacheable_Response_Interface
{
    use Cacheable_Response_Trait;
}