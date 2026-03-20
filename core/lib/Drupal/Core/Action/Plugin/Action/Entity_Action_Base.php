<?php

declare (strict_types=1);
namespace Drupal\Core\Action\Plugin\Action;

use Drupal\Component\Plugin\Dependent_Plugin_Interface;
use Drupal\Core\Action\Action_Base;
use Drupal\Core\Plugin\Container_Factory_Plugin_Interface;
/**
 * Base class for entity-based actions.
 */
abstract class Entity_Action_Base extends Action_Base implements Dependent_Plugin_Interface, Container_Factory_Plugin_Interface
{
    /**
     * Constructs an EntityActionBase object.
     *
     * @param mixed[] $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, protected \Drupal\Core\Entity\Entity_Type_Manager_Interface $entity_type_manager)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }
    /**
     * {@inheritdoc}
     */
    public function calculate_dependencies()
    {
        $module_name = $this->entity_type_manager->get_definition($this->get_plugin_definition()['type'])->get_provider();
        return ['module' => [$module_name]];
    }
}