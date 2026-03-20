<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Memory_Cache;

use Drupal\Core\Cache\Cache_Backend_Interface;
use Drupal\Core\Cache\Cache_Tags_Invalidator_Interface;
/**
 * Defines an interface for memory cache implementations.
 *
 * This has additional requirements over CacheBackendInterface and
 * CacheTagsInvalidatorInterface. Objects stored must be the same instance when
 * retrieved from cache, so that this can be used as a replacement for protected
 * properties and similar.
 *
 * @ingroup cache
 */
interface Memory_Cache_Interface extends Cache_Backend_Interface, Cache_Tags_Invalidator_Interface
{
}