<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

use Drupal\Core\Extension\Module_Handler_Interface;
use Drupal\Core\Field\Field_Definition_Interface;
use Drupal\Core\Field\Field_Item_List_Interface;
use Drupal\Core\Session\Account_Interface;
/**
 * Defines an interface for entity access control handlers.
 */
interface Entity_Access_Control_Handler_Interface
{
    /**
     * Checks access to an operation on a given entity or entity translation.
     *
     * Use \Drupal\Core\Entity\EntityAccessControlHandlerInterface::createAccess()
     * to check access to create an entity.
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity for which to check access.
     * @param string $operation
     *   The operation access should be checked for.
     *   Usually one of "view", "view label", "update" or "delete".
     * @param \Drupal\Core\Session\AccountInterface $account
     *   (optional) The user session for which to check access, or NULL to check
     *   access for the current user. Defaults to NULL.
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
    public function access(Entity_Interface $entity, $operation, ?Account_Interface $account = null, $return_as_object = false);
    /**
     * Checks access to create an entity.
     *
     * @param string $entity_bundle
     *   (optional) The bundle of the entity. Required if the entity supports
     *   bundles, defaults to NULL otherwise.
     * @param \Drupal\Core\Session\AccountInterface $account
     *   (optional) The user session for which to check access, or NULL to check
     *   access for the current user. Defaults to NULL.
     * @param array $context
     *   (optional) An array of key-value pairs to pass additional context when
     *   needed.
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
    public function create_access($entity_bundle = null, ?Account_Interface $account = null, array $context = [], $return_as_object = false);
    /**
     * Clears all cached access checks.
     */
    public function reset_cache();
    /**
     * Sets the module handler for this access control handler.
     *
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
     *   The module handler.
     *
     * @return $this
     */
    public function set_module_handler(Module_Handler_Interface $module_handler);
    /**
     * Checks access to an operation on a given entity field.
     *
     * This method does not determine whether access is granted to the entity
     * itself, only the specific field. Callers are responsible for ensuring that
     * entity access is also respected, for example by using
     * \Drupal\Core\Entity\EntityAccessControlHandlerInterface::access().
     *
     * @param string $operation
     *   The operation access should be checked for. Usually one of "view" or
     *   "edit". Unlike entity access, for field access there is no distinction
     *   between creating and updating.
     * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
     *   The field definition.
     * @param \Drupal\Core\Session\AccountInterface $account
     *   (optional) The user session for which to check access, or NULL to check
     *   access for the current user. Defaults to NULL.
     * @param \Drupal\Core\Field\FieldItemListInterface $items
     *   (optional) The field values for which to check access, or NULL if access
     *    is checked for the field definition, without any specific value
     *    available. Defaults to NULL.
     * @param bool $return_as_object
     *   (optional) Defaults to FALSE.
     *
     * @return ($return_as_object is true ? \Drupal\Core\Access\AccessResultInterface : bool)
     *   The access result. Returns a boolean if $return_as_object is FALSE (this
     *   is the default) and otherwise an AccessResultInterface object.
     *   When a boolean is returned, the result of AccessInterface::isAllowed() is
     *   returned, i.e. TRUE means access is explicitly allowed, FALSE means
     *   access is either explicitly forbidden or "no opinion".
     *
     * @see \Drupal\Core\Entity\EntityAccessControlHandlerInterface::access()
     */
    public function field_access($operation, Field_Definition_Interface $field_definition, ?Account_Interface $account = null, ?Field_Item_List_Interface $items = null, $return_as_object = false);
}