<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Entity\Access;

use Drupal\Core\Access\Access_Result;
use Drupal\Core\Entity\Entity_Access_Control_Handler;
use Drupal\Core\Entity\Entity_Interface;
use Drupal\Core\Session\Account_Interface;
/**
 * Provides an entity access control handler for displays.
 */
class Entity_View_Display_Access_Control_Handler extends Entity_Access_Control_Handler
{
    /**
     * {@inheritdoc}
     */
    protected function check_access(Entity_Interface $entity, $operation, Account_Interface $account)
    {
        /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $entity */
        return parent::check_access($entity, $operation, $account)->or_if(Access_Result::allowed_if_has_permission($account, 'administer ' . $entity->get_target_entity_type_id() . ' display'));
    }
}