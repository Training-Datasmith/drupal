<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Action\Plugin\Config_Action\Deriver;

use Drupal\Component\Plugin\Derivative\Deriver_Base;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Drupal\Core\Plugin\Discovery\Container_Deriver_Interface;
use Symfony\Component\Dependency_Injection\Container;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * @internal
 *   This API is experimental.
 */
final class Permissions_Per_Bundle_Deriver extends Deriver_Base implements Container_Deriver_Interface
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
    public function get_derivative_definitions($base_plugin_definition)
    {
        foreach ($this->entity_type_manager->get_definitions() as $id => $entity_type) {
            if ($entity_type->get_permission_granularity() === 'bundle' && ($bundle_entity_type = $entity_type->get_bundle_entity_type()) !== null) {
                // Convert unique plugin IDs, like `taxonomy_vocabulary`, into strings
                // like `TaxonomyVocabulary`.
                $suffix = Container::camelize($bundle_entity_type);
                $this->derivatives["grantPermissionsForEach{$suffix}"] = ['target_entity_type' => $id] + $base_plugin_definition;
            }
        }
        return parent::get_derivative_definitions($base_plugin_definition);
    }
}