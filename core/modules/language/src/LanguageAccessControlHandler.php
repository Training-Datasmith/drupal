<?php

namespace Drupal\language;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the language entity type.
 *
 * @see \Drupal\language\Entity\ConfigurableLanguage
 */
class LanguageAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return match ($operation) {
        'view' => parent::checkAccess($entity, $operation, $account),
        /** @var \Drupal\Core\Language\LanguageInterface $entity */
        'update' => AccessResult::allowedIf(!$entity->isLocked())->addCacheableDependency($entity)
          ->andIf(parent::checkAccess($entity, $operation, $account)),
        /** @var \Drupal\Core\Language\LanguageInterface $entity */
        'delete' => AccessResult::allowedIf(!$entity->isLocked())->addCacheableDependency($entity)
          ->andIf(AccessResult::allowedIf(!$entity->isDefault())->addCacheableDependency($entity))
          ->andIf(parent::checkAccess($entity, $operation, $account)),
        // No opinion.
        default => AccessResult::neutral(),
    };
  }

}
