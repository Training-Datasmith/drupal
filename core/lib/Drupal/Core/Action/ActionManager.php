<?php

declare (strict_types=1);
namespace Drupal\Core\Action;

use Drupal\Component\Plugin\Categorizing_Plugin_Manager_Interface;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Cache\Cache_Backend_Interface;
use Drupal\Core\Extension\Module_Handler_Interface;
use Drupal\Core\Plugin\Categorizing_Plugin_Manager_Trait;
use Drupal\Core\Plugin\Default_Plugin_Manager;
/**
 * Provides an Action plugin manager.
 *
 * @see \Drupal\Core\Annotation\Action
 * @see \Drupal\Core\Action\ActionInterface
 * @see \Drupal\Core\Action\ActionBase
 * @see plugin_api
 */
class Action_Manager extends Default_Plugin_Manager implements Categorizing_Plugin_Manager_Interface
{
    use Categorizing_Plugin_Manager_Trait;
    /**
     * Constructs a new class instance.
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
        parent::__construct('Plugin/Action', $namespaces, $module_handler, Action_Interface::class, Action::class, \Drupal\Core\Annotation\Action::class);
        $this->alter_info('action_info');
        $this->set_cache_backend($cache_backend, 'action_info');
    }
    /**
     * Gets the plugin definitions for this entity type.
     *
     * @param string $type
     *   The entity type name.
     *
     * @return array
     *   An array of plugin definitions for this entity type.
     */
    public function get_definitions_by_type($type): array
    {
        return array_filter($this->get_definitions(), fn(array $definition) => $definition['type'] === $type);
    }
}