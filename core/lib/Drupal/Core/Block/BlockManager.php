<?php

declare (strict_types=1);
namespace Drupal\Core\Block;

use Drupal\Component\Plugin\Fallback_Plugin_Manager_Interface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\Cache_Backend_Interface;
use Drupal\Core\Extension\Module_Handler_Interface;
use Drupal\Core\Plugin\Categorizing_Plugin_Manager_Trait;
use Drupal\Core\Plugin\Default_Plugin_Manager;
use Drupal\Core\Plugin\Filtered_Plugin_Manager_Trait;
/**
 * Manages discovery and instantiation of block plugins.
 *
 * @todo Add documentation to this class.
 *
 * @see \Drupal\Core\Block\BlockPluginInterface
 */
class Block_Manager extends Default_Plugin_Manager implements Block_Manager_Interface, Fallback_Plugin_Manager_Interface
{
    use Categorizing_Plugin_Manager_Trait {
        getSortedDefinitions as traitGetSortedDefinitions;
    }
    use Filtered_Plugin_Manager_Trait;
    /**
     * Constructs a new \Drupal\Core\Block\BlockManager object.
     *
     * @param \Traversable $namespaces
     *   An object that implements \Traversable which contains the root paths
     *   keyed by the corresponding namespace to look for plugin implementations.
     * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
     *   Cache backend instance to use.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
     *   The module handler to invoke the alter hook with.
     * @param \Psr\Log\LoggerInterface $logger
     *   The logger.
     */
    public function __construct(\Traversable $namespaces, Cache_Backend_Interface $cache_backend, Module_Handler_Interface $module_handler, protected \Psr\Log\Logger_Interface $logger)
    {
        parent::__construct('Plugin/Block', $namespaces, $module_handler, Block_Plugin_Interface::class, Block::class, \Drupal\Core\Block\Annotation\Block::class);
        $this->alter_info($this->get_type());
        $this->set_cache_backend($cache_backend, 'block_plugins');
    }
    /**
     * {@inheritdoc}
     */
    protected function get_type(): string
    {
        return 'block';
    }
    /**
     * {@inheritdoc}
     */
    public function process_definition(&$definition, $plugin_id): void
    {
        parent::process_definition($definition, $plugin_id);
        $this->process_definition_category($definition);
    }
    /**
     * {@inheritdoc}
     */
    public function get_sorted_definitions(?array $definitions = null, string $label_key = 'label')
    {
        // Sort the plugins first by category, then by admin label.
        $definitions = $this->trait_get_sorted_definitions($definitions, 'admin_label');
        // Do not display the 'broken' plugin in the UI.
        unset($definitions['broken']);
        return $definitions;
    }
    /**
     * {@inheritdoc}
     */
    public function get_fallback_plugin_id($plugin_id, array $configuration = []): string
    {
        return 'broken';
    }
    /**
     * {@inheritdoc}
     */
    protected function handle_plugin_not_found($plugin_id, array $configuration)
    {
        $this->logger->warning('The "%plugin_id" block plugin was not found', ['%plugin_id' => $plugin_id]);
        return parent::handle_plugin_not_found($plugin_id, $configuration);
    }
}