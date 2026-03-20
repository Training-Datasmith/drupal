<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection;

use Drupal\Component\Dependency_Injection\Reverse_Container;
use Drupal\Core\Entity\Entity_Storage_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
/**
 * Provides dependency injection friendly methods for serialization.
 */
trait Dependency_Serialization_Trait
{
    /**
     * An array of service IDs keyed by property name used for serialization.
     *
     * @var array
     */
    // phpcs:ignore Drupal.Classes.PropertyDeclaration, Drupal.NamingConventions.ValidVariableName.LowerCamelName
    protected $_service_ids = [];
    /**
     * An array of entity type IDs keyed by the property name of their storages.
     *
     * @var array
     */
    // phpcs:ignore Drupal.Classes.PropertyDeclaration, Drupal.NamingConventions.ValidVariableName.LowerCamelName
    protected $_entity_storages = [];
    /**
     * {@inheritdoc}
     */
    public function __sleep(): array
    {
        $vars = get_object_vars($this);
        try {
            $container = \Drupal::get_container();
            $reverse_container = $container->get(Reverse_Container::class);
            foreach ($vars as $key => $value) {
                if (!is_object($value)) {
                    // Ignore properties that cannot be services.
                    continue;
                }
                if ($value instanceof Translatable_Markup) {
                    // Ignore properties that cannot be services.
                    continue;
                }
                if ($value instanceof Entity_Storage_Interface) {
                    // If a class member is an entity storage, only store the entity type
                    // ID the storage is for, so it can be used to get a fresh object on
                    // unserialization. By doing this we prevent possible memory leaks
                    // when the storage is serialized and it contains a static cache of
                    // entity objects. Additionally we ensure that we'll not have multiple
                    // storage objects for the same entity type and therefore prevent
                    // returning different references for the same entity.
                    $this->_entity_storages[$key] = $value->get_entity_type_id();
                    unset($vars[$key]);
                } elseif ($service_id = $reverse_container->get_id($value)) {
                    // If a class member was instantiated by the dependency injection
                    // container, only store its ID so it can be used to get a fresh
                    // object on unserialization.
                    $this->_service_ids[$key] = $service_id;
                    unset($vars[$key]);
                }
            }
        } catch (Container_Not_Initialized_Exception) {
            // No container, no problem.
        }
        return array_keys($vars);
    }
    /**
     * {@inheritdoc}
     */
    public function __wakeup(): void
    {
        // Avoid trying to wakeup if there's nothing to do.
        if (empty($this->_service_ids) && empty($this->_entity_storages)) {
            return;
        }
        $container = \Drupal::get_container();
        foreach ($this->_service_ids as $key => $service_id) {
            $this->{$key} = $container->get($service_id);
        }
        $this->_service_ids = [];
        if ($this->_entity_storages) {
            /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
            $entity_type_manager = $container->get('entity_type.manager');
            foreach ($this->_entity_storages as $key => $entity_type_id) {
                $this->{$key} = $entity_type_manager->get_storage($entity_type_id);
            }
        }
        $this->_entity_storages = [];
    }
}