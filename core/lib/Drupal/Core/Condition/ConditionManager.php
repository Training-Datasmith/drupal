<?php

declare (strict_types=1);
namespace Drupal\Core\Condition;

use Drupal\Component\Plugin\Categorizing_Plugin_Manager_Interface;
use Drupal\Core\Cache\Cache_Backend_Interface;
use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Executable\Executable_Exception;
use Drupal\Core\Executable\Executable_Interface;
use Drupal\Core\Executable\Executable_Manager_Interface;
use Drupal\Core\Extension\Module_Handler_Interface;
use Drupal\Core\Plugin\Categorizing_Plugin_Manager_Trait;
use Drupal\Core\Plugin\Default_Plugin_Manager;
use Drupal\Core\Plugin\Filtered_Plugin_Manager_Interface;
use Drupal\Core\Plugin\Filtered_Plugin_Manager_Trait;
/**
 * A plugin manager for condition plugins.
 *
 * @see \Drupal\Core\Condition\Attribute\Condition
 * @see \Drupal\Core\Condition\ConditionInterface
 * @see \Drupal\Core\Condition\ConditionPluginBase
 *
 * @ingroup plugin_api
 */
class Condition_Manager extends Default_Plugin_Manager implements Executable_Manager_Interface, Categorizing_Plugin_Manager_Interface, Filtered_Plugin_Manager_Interface
{
    use Categorizing_Plugin_Manager_Trait;
    use Filtered_Plugin_Manager_Trait;
    /**
     * Constructs a ConditionManager object.
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
        $this->alter_info('condition_info');
        $this->set_cache_backend($cache_backend, 'condition_plugins');
        parent::__construct('Plugin/Condition', $namespaces, $module_handler, Condition_Interface::class, Condition::class, \Drupal\Core\Condition\Annotation\Condition::class);
    }
    /**
     * {@inheritdoc}
     */
    protected function get_type(): string
    {
        return 'condition';
    }
    /**
     * {@inheritdoc}
     */
    public function create_instance($plugin_id, array $configuration = [])
    {
        $plugin = $this->get_factory()->create_instance($plugin_id, $configuration);
        return $plugin->set_executable_manager($this);
    }
    /**
     * {@inheritdoc}
     */
    public function execute(Executable_Interface $condition)
    {
        if ($condition instanceof Condition_Interface) {
            $result = $condition->evaluate();
            return $condition->is_negated() ? !$result : $result;
        }
        throw new Executable_Exception('This manager object can only execute condition plugins');
    }
}