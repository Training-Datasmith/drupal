<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

use Drupal\Core\Access\Access_Result;
use Drupal\Core\Field\Field_Definition_Interface;
use Drupal\Core\Field\Field_Item_List_Interface;
use Drupal\Core\Language\Language_Interface;
use Drupal\Core\Session\Account_Interface;
/**
 * Defines a default implementation for entity access control handler.
 */
class Entity_Access_Control_Handler extends Entity_Handler_Base implements Entity_Access_Control_Handler_Interface
{
    /**
     * Stores calculated access check results.
     *
     * @var array
     */
    protected $access_cache = [];
    /**
     * The entity type ID of the access control handler instance.
     *
     * @var string
     */
    protected $entity_type_id;
    /**
     * Information about the entity type.
     */
    protected \Drupal\Core\Entity\Entity_Type_Interface $entity_type;
    /**
     * Allows to grant access to just the labels.
     *
     * By default, the "view label" operation falls back to "view". Set this to
     * TRUE to allow returning different access when just listing entity labels.
     *
     * @var bool
     */
    protected $view_label_operation = false;
    /**
     * Constructs an access control handler instance.
     *
     * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
     *   The entity type definition.
     */
    public function __construct(Entity_Type_Interface $entity_type)
    {
        $this->entity_type_id = $entity_type->id();
        $this->entity_type = $entity_type;
    }
    /**
     * {@inheritdoc}
     */
    public function access(Entity_Interface $entity, $operation, ?Account_Interface $account = null, $return_as_object = false)
    {
        $account = $this->prepare_user($account);
        $langcode = $entity->language()->get_id();
        if ($operation === 'view label' && $this->view_label_operation == false) {
            $operation = 'view';
        }
        // If an entity does not have a UUID, either from not being set or from not
        // having them, use the 'entity type:ID' pattern as the cache $cid.
        $cid = $entity->uuid() ?: $entity->get_entity_type_id() . ':' . $entity->id();
        // If the entity is revisionable, then append the revision ID to allow
        // individual revisions to have specific access control and be cached
        // separately.
        if ($entity instanceof Revisionable_Interface) {
            /** @var \Drupal\Core\Entity\RevisionableInterface $entity */
            $cid .= ':' . $entity->get_revision_id();
            // It is not possible to delete or revert the default revision.
            if ($entity->is_default_revision() && ($operation === 'revert' || $operation === 'delete revision')) {
                return $return_as_object ? Access_Result::forbidden() : false;
            }
        }
        if (($return = $this->get_cache($cid, $operation, $langcode, $account)) !== null) {
            // Cache hit, no work necessary.
            return $return_as_object ? $return : $return->is_allowed();
        }
        // Invoke hook_entity_access() and hook_ENTITY_TYPE_access(). Hook results
        // take precedence over overridden implementations of
        // EntityAccessControlHandler::checkAccess(). Entities that have checks that
        // need to be done before the hook is invoked should do so by overriding
        // this method.
        // We grant access to the entity if both of these conditions are met:
        // - No modules say to deny access.
        // - At least one module says to grant access.
        $access = array_merge($this->module_handler()->invoke_all('entity_access', [$entity, $operation, $account]), $this->module_handler()->invoke_all($entity->get_entity_type_id() . '_access', [$entity, $operation, $account]));
        $return = $this->process_access_hook_results($access);
        // Also execute the default access check except when the access result is
        // already forbidden, as in that case, it can not be anything else.
        if (!$return->is_forbidden()) {
            $return = $return->or_if($this->check_access($entity, $operation, $account));
        }
        $result = $this->set_cache($return, $cid, $operation, $langcode, $account);
        return $return_as_object ? $result : $result->is_allowed();
    }
    /**
     * Determines entity access.
     *
     * We grant access to the entity if both of these conditions are met:
     * - No modules say to deny access.
     * - At least one module says to grant access.
     *
     * @param \Drupal\Core\Access\AccessResultInterface[] $access
     *   An array of access results of the fired access hook.
     *
     * @return \Drupal\Core\Access\AccessResultInterface
     *   The combined result of the various access checks' results. All their
     *   cacheability metadata is merged as well.
     *
     * @see \Drupal\Core\Access\AccessResultInterface::orIf()
     */
    protected function process_access_hook_results(array $access)
    {
        // No results means no opinion.
        if (empty($access)) {
            return Access_Result::neutral();
        }
        /** @var \Drupal\Core\Access\AccessResultInterface $result */
        $result = array_shift($access);
        foreach ($access as $other) {
            $result = $result->or_if($other);
        }
        return $result;
    }
    /**
     * Performs access checks.
     *
     * This method is supposed to be overwritten by extending classes that
     * do their own custom access checking.
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity for which to check access.
     * @param string $operation
     *   The entity operation. Usually one of 'view', 'view label', 'update' or
     *   'delete'.
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The user for which to check access.
     *
     * @return \Drupal\Core\Access\AccessResultInterface
     *   The access result.
     */
    protected function check_access(Entity_Interface $entity, $operation, Account_Interface $account)
    {
        if ($operation == 'delete' && $entity->is_new()) {
            return Access_Result::forbidden()->add_cacheable_dependency($entity);
        }
        if ($admin_permission = $this->entity_type->get_admin_permission()) {
            return Access_Result::allowed_if_has_permission($account, $admin_permission);
        }
        // No opinion.
        return Access_Result::neutral();
    }
    /**
     * Tries to retrieve a previously cached access value from the static cache.
     *
     * @param string $cid
     *   Unique string identifier for the entity/operation, for example the
     *   entity UUID or a custom string.
     * @param string $operation
     *   The entity operation. Usually one of 'view', 'update', 'create' or
     *   'delete'.
     * @param string $langcode
     *   The language code for which to check access.
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The user for which to check access.
     *
     * @return \Drupal\Core\Access\AccessResultInterface|null
     *   The cached AccessResult, or NULL if there is no record for the given
     *   user, operation, langcode and entity in the cache.
     */
    protected function get_cache($cid, $operation, $langcode, Account_Interface $account)
    {
        // Return from cache if a value has been set for it previously.
        if (isset($this->access_cache[$account->id()][$cid][$langcode][$operation])) {
            return $this->access_cache[$account->id()][$cid][$langcode][$operation];
        }
    }
    /**
     * Statically caches whether the given user has access.
     *
     * @param \Drupal\Core\Access\AccessResultInterface $access
     *   The access result.
     * @param string $cid
     *   Unique string identifier for the entity/operation, for example the
     *   entity UUID or a custom string.
     * @param string $operation
     *   The entity operation. Usually one of 'view', 'update', 'create' or
     *   'delete'.
     * @param string $langcode
     *   The language code for which to check access.
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The user for which to check access.
     *
     * @return \Drupal\Core\Access\AccessResultInterface
     *   Whether the user has access, plus cacheability metadata.
     */
    protected function set_cache($access, $cid, $operation, $langcode, Account_Interface $account)
    {
        // Save the given value in the static cache and directly return it.
        return $this->access_cache[$account->id()][$cid][$langcode][$operation] = $access;
    }
    /**
     * {@inheritdoc}
     */
    public function reset_cache(): void
    {
        $this->access_cache = [];
    }
    /**
     * {@inheritdoc}
     */
    public function create_access($entity_bundle = null, ?Account_Interface $account = null, array $context = [], $return_as_object = false)
    {
        $account = $this->prepare_user($account);
        $context += ['entity_type_id' => $this->entity_type_id, 'langcode' => Language_Interface::LANGCODE_DEFAULT];
        $cid = $this->build_create_access_cid($context, $entity_bundle);
        if ($cid && ($access = $this->get_cache($cid, 'create', $context['langcode'], $account)) !== null) {
            // Cache hit, no work necessary.
            return $return_as_object ? $access : $access->is_allowed();
        }
        // Invoke hook_entity_create_access() and hook_ENTITY_TYPE_create_access().
        // Hook results take precedence over overridden implementations of
        // EntityAccessControlHandler::checkCreateAccess(). Entities that have
        // checks that need to be done before the hook is invoked should do so by
        // overriding this method.
        // We grant access to the entity if both of these conditions are met:
        // - No modules say to deny access.
        // - At least one module says to grant access.
        $access = array_merge($this->module_handler()->invoke_all('entity_create_access', [$account, $context, $entity_bundle]), $this->module_handler()->invoke_all($this->entity_type_id . '_create_access', [$account, $context, $entity_bundle]));
        $return = $this->process_access_hook_results($access);
        // Also execute the default access check except when the access result is
        // already forbidden, as in that case, it can not be anything else.
        if (!$return->is_forbidden()) {
            $return = $return->or_if($this->check_create_access($account, $context, $entity_bundle));
        }
        $result = $cid ? $this->set_cache($return, $cid, 'create', $context['langcode'], $account) : $return;
        return $return_as_object ? $result : $result->is_allowed();
    }
    /**
     * Performs create access checks.
     *
     * This method is supposed to be overwritten by extending classes that
     * do their own custom access checking.
     *
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The user for which to check access.
     * @param array $context
     *   An array of key-value pairs to pass additional context when needed.
     * @param string|null $entity_bundle
     *   (optional) The bundle of the entity. Required if the entity supports
     *   bundles, defaults to NULL otherwise.
     *
     * @return \Drupal\Core\Access\AccessResultInterface
     *   The access result.
     */
    protected function check_create_access(Account_Interface $account, array $context, $entity_bundle = null)
    {
        if ($admin_permission = $this->entity_type->get_admin_permission()) {
            return Access_Result::allowed_if_has_permission($account, $admin_permission);
        }
        // No opinion.
        return Access_Result::neutral();
    }
    /**
     * Loads the current account object, if it does not exist yet.
     *
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The account interface instance.
     *
     * @return \Drupal\Core\Session\AccountInterface
     *   Returns the current account object.
     */
    protected function prepare_user(?Account_Interface $account = null)
    {
        if (!$account) {
            return \Drupal::current_user();
        }
        return $account;
    }
    /**
     * {@inheritdoc}
     */
    public function field_access($operation, Field_Definition_Interface $field_definition, ?Account_Interface $account = null, ?Field_Item_List_Interface $items = null, $return_as_object = false)
    {
        $account = $this->prepare_user($account);
        // Get the default access restriction that lives within this field.
        $default = $items ? $items->default_access($operation, $account) : Access_Result::allowed();
        // Explicitly disallow changing the entity ID and entity UUID.
        $entity = $items ? $items->get_entity() : null;
        if ($operation === 'edit' && $entity) {
            if ($field_definition->get_name() === $this->entity_type->get_key('id')) {
                // String IDs can be set when creating the entity.
                if (!($entity->is_new() && $field_definition->get_type() === 'string')) {
                    return $return_as_object ? Access_Result::forbidden('The entity ID cannot be changed.')->add_cacheable_dependency($entity) : false;
                }
            } elseif ($field_definition->get_name() === $this->entity_type->get_key('uuid')) {
                // UUIDs can be set when creating an entity.
                if (!$entity->is_new()) {
                    return $return_as_object ? Access_Result::forbidden('The entity UUID cannot be changed.')->add_cacheable_dependency($entity) : false;
                }
            }
        }
        // Get the default access restriction as specified by the access control
        // handler.
        $entity_default = $this->check_field_access($operation, $field_definition, $account, $items);
        // Combine default access, denying access wins.
        $default = $default->and_if($entity_default);
        // Invoke hook and collect grants/denies for field access from other
        // modules.
        $grants = [];
        $this->module_handler()->invoke_all_with('entity_field_access', function (callable $hook, string $module) use ($operation, $field_definition, $account, $items, &$grants): void {
            $grants[] = [$module => $hook($operation, $field_definition, $account, $items)];
        });
        // Our default access flag is masked under the ':default' key.
        $grants = array_merge([':default' => $default], ...$grants);
        // Also allow modules to alter the returned grants/denies.
        $context = ['operation' => $operation, 'field_definition' => $field_definition, 'items' => $items, 'account' => $account];
        $this->module_handler()->alter('entity_field_access', $grants, $context);
        $result = $this->process_access_hook_results($grants);
        return $return_as_object ? $result : $result->is_allowed();
    }
    /**
     * Default field access as determined by this access control handler.
     *
     * Most fields return AccessResultAllowed by default. It is recommended to use
     * it in conjunction with entity access conditions for robust access control.
     *
     * @param string $operation
     *   The operation access should be checked for.
     *   Usually one of "view" or "edit".
     * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
     *   The field definition.
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The user session for which to check access.
     * @param \Drupal\Core\Field\FieldItemListInterface $items
     *   (optional) The field values for which to check access, or NULL if access
     *   is checked for the field definition, without any specific value
     *   available. Defaults to NULL.
     *
     * @return \Drupal\Core\Access\AccessResultInterface
     *   The access result.
     */
    protected function check_field_access($operation, Field_Definition_Interface $field_definition, Account_Interface $account, ?Field_Item_List_Interface $items = null)
    {
        if (!$items instanceof Field_Item_List_Interface || $operation !== 'view') {
            return Access_Result::allowed();
        }
        $entity = $items->get_entity();
        $is_revision_log_field = $this->entity_type instanceof Content_Entity_Type_Interface && $field_definition->get_name() === $this->entity_type->get_revision_metadata_key('revision_log_message');
        if ($entity && $is_revision_log_field) {
            // The revision log should only be visible to those who can view the
            // revisions OR edit the entity.
            return $entity->access('view revision', $account, true)->or_if($entity->access('update', $account, true));
        }
        return Access_Result::allowed();
    }
    /**
     * Builds the create access result cache ID.
     *
     * If there is no context other than langcode and entity type id, then the
     * cache id can be simply the bundle. Otherwise, a custom implementation is
     * needed to ensure cacheability, and the default implementation here
     * returns null.
     *
     * @param array $context
     *   The create access context.
     * @param string|null $entity_bundle
     *   The entity bundle, if the entity type has bundles.
     *
     * @return string|null
     *   The create access result cache ID, or null if uncacheable.
     */
    protected function build_create_access_cid(array $context, ?string $entity_bundle): ?string
    {
        $extended_context = array_filter($context, fn($key) => !in_array($key, ['entity_type_id', 'langcode']), ARRAY_FILTER_USE_KEY);
        if (empty($extended_context)) {
            return $entity_bundle ? 'create:' . $entity_bundle : 'create';
        }
        return null;
    }
}