<?php

namespace Drupal\field;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the field storage config entity type.
 *
 * @see \Drupal\field\Entity\FieldStorageConfig
 */
class FieldStorageConfigAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\field\FieldStorageConfigInterface $entity */
    if ($operation !== 'delete') {
      return AccessResult::allowedIfHasPermission($account, 'administer ' . $entity->getTargetEntityTypeId() . ' fields');
    }
    if ($entity->isLocked()) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }
    return AccessResult::allowedIfHasPermission($account, 'administer ' . $entity->getTargetEntityTypeId() . ' fields')->addCacheableDependency($entity);
  }

}
