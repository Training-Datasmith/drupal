<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
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
class User_Roles_Cache_Context extends User_Cache_Context_Base implements Calculated_Cache_Context_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t("User's roles");
    }
    /**
     * {@inheritdoc}
     */
    public function get_context($role = null): string
    {
        if ($role === null) {
            return implode(',', $this->user->get_roles());
        }
        return in_array($role, $this->user->get_roles(), true) ? 'true' : 'false';
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata($role = null)
    {
        return (new Cacheable_Metadata())->set_cache_tags(['user:' . $this->user->id()]);
    }
}