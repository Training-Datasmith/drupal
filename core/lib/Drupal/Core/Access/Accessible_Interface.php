<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

use Drupal\Core\Session\Account_Interface;
/**
 * Interface for checking access.
 *
 * @ingroup entity_api
 */
interface Accessible_Interface
{
    /**
     * Checks data value access.
     *
     * @param string $operation
     *   The operation to be performed.
     * @param \Drupal\Core\Session\AccountInterface $account
     *   (optional) The user for which to check access, or NULL to check access
     *   for the current user. Defaults to NULL.
     * @param bool $return_as_object
     *   (optional) Defaults to FALSE.
     *
     * @return ($return_as_object is true ? \Drupal\Core\Access\AccessResultInterface : bool)
     *   The access result. Returns a boolean if $return_as_object is FALSE (this
     *   is the default) and otherwise an AccessResultInterface object.
     *   When a boolean is returned, the result of AccessInterface::isAllowed() is
     *   returned, i.e. TRUE means access is explicitly allowed, FALSE means
     *   access is either explicitly forbidden or "no opinion".
     */
    public function access($operation, ?Account_Interface $account = null, $return_as_object = false);
}