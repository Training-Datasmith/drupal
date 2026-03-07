<?php

declare(strict_types=1);

namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Defines the UserRolesCacheContext service, for "per role" caching.
 *
 * Only use this cache context when checking explicitly for certain roles. Use
 * user.permissions for anything that checks permissions.
 *
 * Cache context ID: 'user.roles' (to vary by all roles of the current user).
 * Calculated cache context ID: 'user.roles:%role', e.g. 'user.roles:anonymous'
 * (to vary by the presence/absence of a specific role).
 */
class UserRolesCacheContext extends UserCacheContextBase implements CalculatedCacheContextInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getLabel()
    {
        return t("User's roles");
    }

    /**
     * {@inheritdoc}
     */
    public function getContext($role = null): string
    {
        if ($role === null) {
            return implode(',', $this->user->getRoles());
        }

        return in_array($role, $this->user->getRoles(), true) ? 'true' : 'false';
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheableMetadata($role = null)
    {
        return (new CacheableMetadata())->setCacheTags(['user:' . $this->user->id()]);
    }

}
