<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Symfony\Component\Http_Foundation\Redirect_Response;
/**
 * A RedirectResponse that contains and can expose cacheability metadata.
 *
 * Supports Drupal's caching concepts: cache tags for invalidation and cache
 * contexts for variations.
 *
 * @see \Drupal\Core\Cache\Cache
 * @see \Drupal\Core\Cache\CacheableMetadata
 * @see \Drupal\Core\Cache\CacheableResponseTrait
 */
class Cacheable_Redirect_Response extends Redirect_Response implements Cacheable_Response_Interface
{
    use Cacheable_Response_Trait;
}