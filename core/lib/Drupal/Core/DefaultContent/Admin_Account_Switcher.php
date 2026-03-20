<?php

declare (strict_types=1);
namespace Drupal\Core\Default_Content;

use Drupal\Core\Access\Access_Exception;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Drupal\Core\Session\Account_Interface;
use Drupal\Core\Session\Account_Switcher_Interface;
/**
 * @internal
 *   This API is experimental.
 */
final readonly class Admin_Account_Switcher implements Account_Switcher_Interface
{
    public function __construct(private Account_Switcher_Interface $decorated, private Entity_Type_Manager_Interface $entity_type_manager, private bool $is_super_user_access_enabled)
    {
    }
    /**
     * Switches to an administrative account.
     *
     * This will switch to the first available account with a role that has the
     * `is_admin` flag. If there are no such roles, or no such users, this will
     * try to switch to user 1 if superuser access is enabled.
     *
     * @return \Drupal\Core\Session\AccountInterface
     *   The account that was switched to.
     *
     * @throws \Drupal\Core\Access\AccessException
     *   Thrown if there are no users with administrative roles.
     */
    public function switch_to_administrator(): Account_Interface
    {
        $admin_roles = $this->entity_type_manager->get_storage('user_role')->get_query()->condition('is_admin', true)->execute();
        $user_storage = $this->entity_type_manager->get_storage('user');
        if ($admin_roles) {
            $accounts = $user_storage->get_query()->access_check(false)->condition('roles', $admin_roles, 'IN')->condition('status', 1)->sort('uid')->range(0, 1)->execute();
        } else {
            $accounts = [];
        }
        $account = $user_storage->load(reset($accounts) ?: 1);
        assert($account instanceof Account_Interface);
        if (array_intersect($account->get_roles(), $admin_roles) || (int) $account->id() === 1 && $this->is_super_user_access_enabled) {
            $this->switch_to($account);
            return $account;
        }
        throw new Access_Exception('There are no user accounts with administrative roles.');
    }
    /**
     * {@inheritdoc}
     */
    public function switch_to(Account_Interface $account): Account_Switcher_Interface
    {
        $this->decorated->switch_to($account);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function switch_back(): Account_Switcher_Interface
    {
        $this->decorated->switch_back();
        return $this;
    }
}