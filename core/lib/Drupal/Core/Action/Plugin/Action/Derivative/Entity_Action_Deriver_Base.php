<?php

declare (strict_types=1);
namespace Drupal\Core\Action\Plugin\Action\Derivative;

use Drupal\Component\Plugin\Derivative\Deriver_Base;
use Drupal\Core\Entity\Entity_Type_Interface;
use Drupal\Core\Plugin\Discovery\Container_Deriver_Interface;
use Drupal\Core\String_Translation\String_Translation_Trait;
use Drupal\Core\String_Translation\Translation_Interface;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * Provides a base action for each entity type with specific interfaces.
 */
abstract class Entity_Action_Deriver_Base extends Deriver_Base implements Container_Deriver_Interface
{
    use String_Translation_Trait;
    /**
     * Constructs a new EntityActionDeriverBase object.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager.
     * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
     *   The string translation service.
     */
    public function __construct(protected \Drupal\Core\Entity\Entity_Type_Manager_Interface $entity_type_manager, Translation_Interface $string_translation)
    {
        $this->string_translation = $string_translation;
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container, $base_plugin_id)
    {
        return new static($container->get('entity_type.manager'), $container->get('string_translation'));
    }
    /**
     * Indicates whether the deriver can be used for the provided entity type.
     *
     * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
     *   The entity type.
     *
     * @return bool
     *   TRUE if the entity type can be used, FALSE otherwise.
     */
    abstract protected function is_applicable(Entity_Type_Interface $entity_type);
    /**
     * {@inheritdoc}
     */
    public function get_derivative_definitions($base_plugin_definition)
    {
        if (empty($this->derivatives)) {
            $definitions = [];
            foreach ($this->get_applicable_entity_types() as $entity_type_id => $entity_type) {
                $definition = $base_plugin_definition;
                $definition['type'] = $entity_type_id;
                $definition['label'] = sprintf('%s %s', $base_plugin_definition['action_label'], $entity_type->get_singular_label());
                $definitions[$entity_type_id] = $definition;
            }
            $this->derivatives = $definitions;
        }
        return parent::get_derivative_definitions($base_plugin_definition);
    }
    /**
     * Gets a list of applicable entity types.
     *
     * The list consists of all entity types which match the conditions for the
     * given deriver.
     * For example, if the action applies to entities that are publishable,
     * this method will find all entity types that are publishable.
     *
     * @return \Drupal\Core\Entity\EntityTypeInterface[]
     *   The applicable entity types, keyed by entity type ID.
     */
    protected function get_applicable_entity_types()
    {
        $entity_types = $this->entity_type_manager->get_definitions();
        return array_filter($entity_types, fn(Entity_Type_Interface $entity_type) => $this->is_applicable($entity_type));
    }
}