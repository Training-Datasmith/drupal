<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Drupal\Core\Site\Settings;
use Psr\Container\Container_Interface;
/**
 * Defines the cache backend factory.
 */
class Cache_Factory implements Cache_Factory_Interface
{
    /**
     * The service container.
     */
    protected Container_Interface $container;
    /**
     * Sets the service container.
     */
    public function set_container(Container_Interface $container): void
    {
        $this->container = $container;
    }
    /**
     * Constructs CacheFactory object.
     *
     * @param \Drupal\Core\Site\Settings $settings
     *   The site settings.
     * @param array $defaultBinBackends
     *   (optional) A mapping of bin to backend service name. Mappings in
     *   $settings take precedence over this.
     * @param array $memoryDefaultBinBackends
     *   (optional) A mapping of bin to backend service name. Mappings in
     *   $settings take precedence over this.
     */
    public function __construct(protected \Drupal\Core\Site\Settings $settings, protected array $default_bin_backends = [], protected array $memory_default_bin_backends = [])
    {
    }
    /**
     * Instantiates a cache backend class for a given cache bin.
     *
     * By default, this returns an instance of the
     * Drupal\Core\Cache\DatabaseBackend class.
     *
     * Classes implementing Drupal\Core\Cache\CacheBackendInterface can register
     * themselves both as a default implementation and for specific bins.
     *
     * @param string $bin
     *   The cache bin for which a cache backend object should be returned.
     *
     * @return \Drupal\Core\Cache\CacheBackendInterface
     *   The cache backend object associated with the specified bin.
     */
    public function get($bin)
    {
        $cache_settings = $this->settings->get('cache');
        // First, look for a cache bin specific setting.
        if (isset($cache_settings['bins'][$bin])) {
            $service_name = $cache_settings['bins'][$bin];
        } elseif (isset($this->default_bin_backends[$bin])) {
            $service_name = $this->default_bin_backends[$bin];
        } elseif (isset($this->memory_default_bin_backends[$bin])) {
            $service_name = $this->memory_default_bin_backends[$bin];
        } elseif (isset($cache_settings['default'])) {
            $service_name = $cache_settings['default'];
        } else {
            // Fall back to the database backend if nothing else is configured.
            $service_name = 'cache.backend.database';
        }
        return $this->container->get($service_name)->get($bin);
    }
}