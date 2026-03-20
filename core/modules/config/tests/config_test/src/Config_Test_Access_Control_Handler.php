<?php

declare(strict_types=1);

namespace Drupal\config_test;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the config_test entity type.
 *
 * @see \Drupal\config_test\Entity\ConfigTest
 */
class ConfigTestAccessControlHandler extends EntityAccessControlHandler
{
    /**
     * {@inheritdoc}
     */
    public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account)
    {
        return AccessResult::allowed();
    }

    /**
     * {@inheritdoc}
     */
    protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = null)
    {
        return AccessResult::allowed();
    }

}
