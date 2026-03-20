<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Drupal\Core\Installer\Installer_Kernel;
use Drupal\Core\Site\Settings;
use Psr\Container\Container_Interface;
/**
 * Defines the chained fast cache backend factory.
 *
 * @see \Drupal\Core\Cache\ChainedFastBackend
 */
class Chained_Fast_Backend_Factory implements Cache_Factory_Interface
{
    /**
     * The service name of the consistent backend factory.
     *
     * @var string
     */
    protected $consistent_service_name;
    /**
     * The service name of the fast backend factory.
     *
     * @var string
     */
    protected $fast_service_name;
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
     * Constructs ChainedFastBackendFactory object.
     *
     * @param \Drupal\Core\Site\Settings|null $settings
     *   (optional) The settings object.
     * @param string|null $consistent_service_name
     *   (optional) The service name of the consistent backend factory. Defaults
     *   to:
     *   - $settings->get('cache')['default'] (if specified)
     *   - 'cache.backend.database' (if the above isn't specified)
     * @param string|null $fast_service_name
     *   (optional) The service name of the fast backend factory. Defaults to:
     *   - 'cache.backend.apcu' (if the PHP process has APCu enabled)
     *   - NULL (if the PHP process doesn't have APCu enabled)
     */
    public function __construct(?Settings $settings = null, $consistent_service_name = null, $fast_service_name = null)
    {
        // Default the consistent backend to the site's default backend.
        if (!isset($consistent_service_name)) {
            $cache_settings = isset($settings) ? $settings->get('cache') : [];
            $consistent_service_name = $cache_settings['default'] ?? 'cache.backend.database';
        }
        // Default the fast backend to APCu if it's available.
        if (!isset($fast_service_name) && function_exists('apcu_fetch')) {
            $fast_service_name = 'cache.backend.apcu';
        }
        $this->consistent_service_name = $consistent_service_name;
        // Do not use the fast chained backend during installation. In those cases,
        // we expect many cache invalidations and writes, the fast chained cache
        // backend performs badly in such a scenario.
        if (!Installer_Kernel::installation_attempted()) {
            $this->fast_service_name = $fast_service_name;
        }
    }
    /**
     * Instantiates a chained, fast cache backend class for a given cache bin.
     *
     * @param string $bin
     *   The cache bin for which a cache backend object should be returned.
     *
     * @return \Drupal\Core\Cache\CacheBackendInterface
     *   The cache backend object associated with the specified bin.
     */
    public function get($bin)
    {
        // Use the chained backend only if there is a fast backend available and it
        // is not the same as the consistent backend; otherwise, just return the
        // consistent backend directly.
        if (isset($this->fast_service_name) && $this->fast_service_name !== $this->consistent_service_name) {
            return new Chained_Fast_Backend($this->container->get($this->consistent_service_name)->get($bin), $this->container->get($this->fast_service_name)->get($bin), $bin);
        }
        return $this->container->get($this->consistent_service_name)->get($bin);
    }
}