<?php

declare (strict_types=1);
namespace Drupal\Core\Display;

use Drupal\Core\Cache\Cache_Backend_Interface;
use Drupal\Core\Display\Attribute\Display_Variant;
use Drupal\Core\Extension\Module_Handler_Interface;
use Drupal\Core\Plugin\Default_Plugin_Manager;
/**
 * Manages discovery of display variant plugins.
 *
 * @see \Drupal\Core\Display\Attribute\DisplayVariant
 * @see \Drupal\Core\Display\VariantInterface
 * @see \Drupal\Core\Display\VariantBase
 * @see plugin_api
 */
class Variant_Manager extends Default_Plugin_Manager
{
    /**
     * Constructs a new VariantManager.
     *
     * @param \Traversable $namespaces
     *   An object that implements \Traversable which contains the root paths
     *   keyed by the corresponding namespace to look for plugin implementations.
     * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
     *   Cache backend instance to use.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
     *   The module handler to invoke the alter hook with.
     */
    public function __construct(\Traversable $namespaces, Cache_Backend_Interface $cache_backend, Module_Handler_Interface $module_handler)
    {
        parent::__construct('Plugin/DisplayVariant', $namespaces, $module_handler, Variant_Interface::class, Display_Variant::class, \Drupal\Core\Display\Annotation\Display_Variant::class);
        $this->set_cache_backend($cache_backend, 'variant_plugins');
        $this->alter_info('display_variant_plugin');
    }
}