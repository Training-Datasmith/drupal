<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Session\Account_Interface;
/**
 * The account permission cache context for "per permission" caching.
 *
 * Cache context ID: 'user.permissions'.
 */
class Account_Permissions_Cache_Context extends User_Cache_Context_Base implements Cache_Context_Interface
{
    /**
     * Constructs a new UserCacheContext service.
     *
     * @param \Drupal\Core\Session\AccountInterface $user
     *   The current user.
     * @param \Drupal\Core\Session\PermissionsHashGeneratorInterface $permissionsHashGenerator
     *   The permissions hash generator.
     */
    public function __construct(Account_Interface $user, protected \Drupal\Core\Session\Permissions_Hash_Generator_Interface $permissions_hash_generator)
    {
        $this->user = $user;
    }
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t("Account's permissions");
    }
    /**
     * {@inheritdoc}
     */
    public function get_context()
    {
        return $this->permissions_hash_generator->generate($this->user);
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata(): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return $this->permissions_hash_generator->get_cacheable_metadata($this->user);
    }
}