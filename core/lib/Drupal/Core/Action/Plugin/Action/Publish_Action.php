<?php

declare (strict_types=1);
namespace Drupal\Core\Action\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\Plugin\Action\Derivative\Entity_Published_Action_Deriver;
use Drupal\Core\Session\Account_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
/**
 * Publishes an entity.
 */
#[Action(id: 'entity:publish_action', action_label: new Translatable_Markup('Publish'), deriver: Entity_Published_Action_Deriver::class)]
class Publish_Action extends Entity_Action_Base
{
    /**
     * {@inheritdoc}
     */
    public function execute($entity = null): void
    {
        $entity->set_published()->save();
    }
    /**
     * {@inheritdoc}
     */
    public function access($object, ?Account_Interface $account = null, $return_as_object = false)
    {
        $key = $object->get_entity_type()->get_key('published');
        /** @var \Drupal\Core\Entity\EntityInterface $object */
        $result = $object->access('update', $account, true)->and_if($object->{$key}->access('edit', $account, true));
        return $return_as_object ? $result : $result->is_allowed();
    }
}