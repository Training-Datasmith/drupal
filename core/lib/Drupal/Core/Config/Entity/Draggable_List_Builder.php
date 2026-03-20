<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Entity;

use Drupal\Core\Entity\Draggable_List_Builder_Trait;
use Drupal\Core\Entity\Entity_Interface;
use Drupal\Core\Entity\Entity_Storage_Interface;
use Drupal\Core\Entity\Entity_Type_Interface;
use Drupal\Core\Form\Form_Interface;
/**
 * Defines a class to build a draggable listing of configuration entities.
 *
 * To enable this feature, the entity type must define a "weight" key in its
 * entity keys annotation.
 */
abstract class Draggable_List_Builder extends Config_Entity_List_Builder implements Form_Interface
{
    use Draggable_List_Builder_Trait;
    /**
     * {@inheritdoc}
     */
    public function __construct(Entity_Type_Interface $entity_type, Entity_Storage_Interface $storage)
    {
        parent::__construct($entity_type, $storage);
        // Do not inject the form builder for backwards-compatibility.
        $this->form_builder = \Drupal::form_builder();
        // Check if the entity type supports weighting and store the key.
        if ($this->entity_type->has_key('weight')) {
            $this->weight_key = $this->entity_type->get_key('weight');
        }
        // Disable limit to load all entities for full drag-and-drop support.
        $this->limit = false;
    }
    /**
     * {@inheritdoc}
     */
    protected function get_weight(Entity_Interface $entity): int|float
    {
        /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
        return $entity->get($this->weight_key) ?: 0;
    }
    /**
     * {@inheritdoc}
     */
    protected function set_weight(Entity_Interface $entity, int|float $weight): Entity_Interface
    {
        /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
        $entity->set($this->weight_key, $weight);
        return $entity;
    }
}