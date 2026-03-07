<?php

declare(strict_types=1);

namespace Drupal\system;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the menu entity type.
 *
 * @see \Drupal\system\Entity\Menu
 */
class MenuAccessControlHandler extends EntityAccessControlHandler
{
    /**
     * {@inheritdoc}
     */
    protected $viewLabelOperation = true;

    /**
     * {@inheritdoc}
     */
    protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account)
    {
        // There are no restrictions on viewing the label of a menu.
        if ($operation === 'view label') {
            return AccessResult::allowed();
        }
        // There are no restrictions on viewing the label of a menu.
        if ($operation === 'delete') {
            if ($entity->isLocked()) {
                return AccessResult::forbidden('The Menu config entity is locked.')->addCacheableDependency($entity);
            }
            return parent::checkAccess($entity, $operation, $account)->addCacheableDependency($entity);
        }

        return parent::checkAccess($entity, $operation, $account);
    }

}
