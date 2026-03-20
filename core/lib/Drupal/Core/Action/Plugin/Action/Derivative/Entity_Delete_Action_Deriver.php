<?php

declare (strict_types=1);
namespace Drupal\Core\Action\Plugin\Action\Derivative;

use Drupal\Core\Entity\Entity_Type_Interface;
/**
 * Provides an action deriver that finds entity types with delete form.
 *
 * @see \Drupal\Core\Action\Plugin\Action\DeleteAction
 */
class Entity_Delete_Action_Deriver extends Entity_Action_Deriver_Base
{
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
                $definition['label'] = $this->t('Delete @entity_type', ['@entity_type' => $entity_type->get_singular_label()]);
                $definition['confirm_form_route_name'] = 'entity.' . $entity_type->id() . '.delete_multiple_form';
                $definitions[$entity_type_id] = $definition;
            }
            $this->derivatives = $definitions;
        }
        return $this->derivatives;
    }
    /**
     * {@inheritdoc}
     */
    protected function is_applicable(Entity_Type_Interface $entity_type)
    {
        return $entity_type->has_link_template('delete-multiple-form');
    }
}