<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Action\Plugin\Config_Action\Deriver;

use Drupal\Component\Plugin\Derivative\Deriver_Base;
use Drupal\Core\Entity\Entity_Type_Interface;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Drupal\Core\Plugin\Discovery\Container_Deriver_Interface;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * Generates derivatives for the create_for_each_bundle config action.
 *
 * @internal
 *   This API is experimental.
 */
final class Create_For_Each_Bundle_Deriver extends Deriver_Base implements Container_Deriver_Interface
{
    public function __construct(private readonly Entity_Type_Manager_Interface $entity_type_manager)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container, $base_plugin_id): static
    {
        return new static($container->get(Entity_Type_Manager_Interface::class));
    }
    /**
     * {@inheritdoc}
     */
    public function get_derivative_definitions($base_plugin_definition): array
    {
        // The action should only be available for entity types that are bundles of
        // another entity type, such as node types, media types, taxonomy
        // vocabularies, and so forth.
        $bundle_entity_types = array_filter($this->entity_type_manager->get_definitions(), fn(Entity_Type_Interface $entity_type): bool => is_string($entity_type->get_bundle_of()));
        $base_plugin_definition['entity_types'] = array_keys($bundle_entity_types);
        $this->derivatives['createForEachIfNotExists'] = $base_plugin_definition + ['create_action' => 'createIfNotExists'];
        $this->derivatives['createForEach'] = $base_plugin_definition + ['create_action' => 'create'];
        return $this->derivatives;
    }
}