<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

/**
 * An interface defining variation cache factory classes.
 */
interface Variation_Cache_Factory_Interface
{
    /**
     * Gets a variation cache for a given cache bin.
     *
     * @param string $bin
     *   The cache bin for which a variation cache should be returned.
     *
     * @return \Drupal\Core\Cache\VariationCacheInterface
     *   The variation cache associated with the specified bin.
     */
    public function get($bin);
}