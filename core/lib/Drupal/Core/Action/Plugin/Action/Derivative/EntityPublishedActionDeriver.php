<?php

declare (strict_types=1);
namespace Drupal\Core\Action\Plugin\Action\Derivative;

use Drupal\Core\Entity\Entity_Published_Interface;
use Drupal\Core\Entity\Entity_Type_Interface;
/**
 * Provides an action deriver that finds publishable entity types.
 *
 * @see \Drupal\Core\Action\Plugin\Action\PublishAction
 * @see \Drupal\Core\Action\Plugin\Action\UnpublishAction
 */
class Entity_Published_Action_Deriver extends Entity_Action_Deriver_Base
{
    /**
     * {@inheritdoc}
     */
    protected function is_applicable(Entity_Type_Interface $entity_type)
    {
        return $entity_type->entity_class_implements(Entity_Published_Interface::class);
    }
}