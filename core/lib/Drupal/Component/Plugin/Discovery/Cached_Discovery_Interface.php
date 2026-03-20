<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Discovery;

/**
 * Interface for discovery components holding a cache of plugin definitions.
 */
interface Cached_Discovery_Interface extends Discovery_Interface
{
    /**
     * Clears static and persistent plugin definition caches.
     *
     * Don't resort to calling \Drupal::cache()->delete() and friends to make
     * Drupal detect new or updated plugin definitions. Always use this method on
     * the appropriate plugin type's plugin manager!
     */
    public function clear_cached_definitions();
    /**
     * Disable the use of caches.
     *
     * Can be used to ensure that uncached plugin definitions are returned,
     * without invalidating all cached information.
     *
     * This will also remove all local/static caches.
     *
     * @param bool $use_caches
     *   FALSE to not use any caches.
     */
    public function use_caches($use_caches = false);
}