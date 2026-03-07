<?php

declare(strict_types=1);

namespace Drupal\user;

use Drupal\Core\Config\Entity\ConfigEntityStorage;

/**
 * Defines the storage handler class for user roles.
 */
class RoleStorage extends ConfigEntityStorage implements RoleStorageInterface
{
    /**
     * {@inheritdoc}
     */
    public function isPermissionInRoles($permission, array $rids): bool
    {
        foreach ($this->loadMultiple($rids) as $role) {
            /** @var \Drupal\user\RoleInterface $role */
            if ($role->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

}
