<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Cacheable_Dependency_Interface;
use Drupal\Core\Cache\Refinable_Cacheable_Dependency_Interface;
use Drupal\Core\Cache\Refinable_Cacheable_Dependency_Trait;
use Drupal\Core\Session\Account_Interface;
/**
 * Value object for passing an access result with cacheability metadata.
 *
 * The access result itself — excluding the cacheability metadata — is
 * immutable. There are subclasses for each of the three possible access results
 * themselves:
 *
 * @see \Drupal\Core\Access\AccessResultAllowed
 * @see \Drupal\Core\Access\AccessResultForbidden
 * @see \Drupal\Core\Access\AccessResultNeutral
 *
 * When using ::orIf() and ::andIf(), cacheability metadata will be merged
 * accordingly as well.
 */
abstract class Access_Result implements Access_Result_Interface, Refinable_Cacheable_Dependency_Interface
{
    use Refinable_Cacheable_Dependency_Trait;
    /**
     * Creates an AccessResultInterface object with isNeutral() === TRUE.
     *
     * @param string|null $reason
     *   (optional) The reason why access is neutral. Intended for developers,
     *   hence not translatable.
     *
     * @return \Drupal\Core\Access\AccessResultNeutral
     *   isNeutral() will be TRUE.
     */
    public static function neutral($reason = null)
    {
        assert(is_string($reason) || is_null($reason));
        return new Access_Result_Neutral($reason);
    }
    /**
     * Creates an AccessResultInterface object with isAllowed() === TRUE.
     *
     * @return \Drupal\Core\Access\AccessResultAllowed
     *   isAllowed() will be TRUE.
     */
    public static function allowed()
    {
        return new Access_Result_Allowed();
    }
    /**
     * Creates an AccessResultInterface object with isForbidden() === TRUE.
     *
     * @param string|null $reason
     *   (optional) The reason why access is forbidden. Intended for developers,
     *   hence not translatable.
     *
     * @return \Drupal\Core\Access\AccessResultForbidden
     *   isForbidden() will be TRUE.
     */
    public static function forbidden($reason = null)
    {
        assert(is_string($reason) || is_null($reason));
        return new Access_Result_Forbidden($reason);
    }
    /**
     * Creates an allowed or neutral access result.
     *
     * @param bool $condition
     *   The condition to evaluate.
     *
     * @return \Drupal\Core\Access\AccessResult
     *   If $condition is TRUE, isAllowed() will be TRUE, otherwise isNeutral()
     *   will be TRUE.
     */
    public static function allowed_if($condition)
    {
        return $condition ? static::allowed() : static::neutral();
    }
    /**
     * Creates a forbidden or neutral access result.
     *
     * @param bool $condition
     *   The condition to evaluate.
     * @param string|null $reason
     *   (optional) The reason why access is forbidden. Intended for developers,
     *   hence not translatable.
     *
     * @return \Drupal\Core\Access\AccessResult
     *   If $condition is TRUE, isForbidden() will be TRUE, otherwise isNeutral()
     *   will be TRUE.
     */
    public static function forbidden_if($condition, $reason = null)
    {
        return $condition ? static::forbidden($reason) : static::neutral();
    }
    /**
     * Creates an access result if the permission is present, neutral otherwise.
     *
     * Checks the permission and adds a 'user.permissions' cache context.
     *
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The account for which to check a permission.
     * @param string $permission
     *   The permission to check for.
     *
     * @return \Drupal\Core\Access\AccessResult
     *   If the account has the permission, isAllowed() will be TRUE, otherwise
     *   isNeutral() will be TRUE.
     */
    public static function allowed_if_has_permission(Account_Interface $account, string $permission)
    {
        $access_result = static::allowed_if($account->has_permission($permission))->add_cache_contexts(['user.permissions']);
        if ($access_result instanceof Access_Result_Reason_Interface) {
            $access_result->set_reason("The '{$permission}' permission is required.");
        }
        return $access_result;
    }
    /**
     * Creates an access result if the permissions are present, neutral otherwise.
     *
     * Checks the permission and adds a 'user.permissions' cache contexts.
     *
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The account for which to check permissions.
     * @param array $permissions
     *   The permissions to check.
     * @param string $conjunction
     *   (optional) 'AND' if all permissions are required, 'OR' in case just one.
     *   Defaults to 'AND'.
     *
     * @return \Drupal\Core\Access\AccessResult
     *   If the account has the permissions, isAllowed() will be TRUE, otherwise
     *   isNeutral() will be TRUE.
     */
    public static function allowed_if_has_permissions(Account_Interface $account, array $permissions, $conjunction = 'AND')
    {
        $access = false;
        if ($conjunction == 'AND' && !empty($permissions)) {
            $access = true;
            foreach ($permissions as $permission) {
                if (!$account->has_permission($permission)) {
                    $access = false;
                    break;
                }
            }
        } else {
            foreach ($permissions as $permission) {
                if ($account->has_permission($permission)) {
                    $access = true;
                    break;
                }
            }
        }
        $access_result = static::allowed_if($access)->add_cache_contexts(empty($permissions) ? [] : ['user.permissions']);
        if ($access_result instanceof Access_Result_Reason_Interface) {
            if (count($permissions) === 1) {
                $access_result->set_reason("The '{$permission}' permission is required.");
            } elseif (count($permissions) > 1) {
                $quote = fn($s) => "'{$s}'";
                $access_result->set_reason(sprintf('The following permissions are required: %s.', implode(" {$conjunction} ", array_map($quote, $permissions))));
            }
        }
        return $access_result;
    }
    /**
     * {@inheritdoc}
     *
     * @see \Drupal\Core\Access\AccessResultAllowed
     */
    public function is_allowed()
    {
        return false;
    }
    /**
     * {@inheritdoc}
     *
     * @see \Drupal\Core\Access\AccessResultForbidden
     */
    public function is_forbidden()
    {
        return false;
    }
    /**
     * {@inheritdoc}
     *
     * @see \Drupal\Core\Access\AccessResultNeutral
     */
    public function is_neutral()
    {
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_contexts()
    {
        return $this->cache_contexts;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_tags()
    {
        return $this->cache_tags;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_max_age()
    {
        return $this->cache_max_age;
    }
    /**
     * Resets cache contexts (to the empty array).
     *
     * @return $this
     */
    public function reset_cache_contexts()
    {
        $this->cache_contexts = [];
        return $this;
    }
    /**
     * Resets cache tags (to the empty array).
     *
     * @return $this
     */
    public function reset_cache_tags()
    {
        $this->cache_tags = [];
        return $this;
    }
    /**
     * Sets the maximum age for which this access result may be cached.
     *
     * @param int $max_age
     *   The maximum time in seconds that this access result may be cached.
     *
     * @return $this
     */
    public function set_cache_max_age($max_age)
    {
        $this->cache_max_age = $max_age;
        return $this;
    }
    /**
     * Convenience method, adds the "user.permissions" cache context.
     *
     * @return $this
     */
    public function cache_per_permissions()
    {
        $this->add_cache_contexts(['user.permissions']);
        return $this;
    }
    /**
     * Convenience method, adds the "user" cache context.
     *
     * @return $this
     */
    public function cache_per_user()
    {
        $this->add_cache_contexts(['user']);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function or_if(Access_Result_Interface $other)
    {
        $merge_other = false;
        // $other's cacheability metadata is merged if $merge_other gets set to TRUE
        // and this happens in three cases:
        // 1. $other's access result is the one that determines the combined access
        //    result.
        // 2. This access result is not cacheable and $other's access result is the
        //    same. i.e. attempt to return a cacheable access result.
        // 3. Neither access result is 'forbidden' and both are cacheable: inherit
        //    the other's cacheability metadata because it may turn into a
        //    'forbidden' for another value of the cache contexts in the
        //    cacheability metadata. In other words: this is necessary to respect
        //    the contagious nature of the 'forbidden' access result.
        //    e.g. we have two access results A and B. Neither is forbidden. A is
        //    globally cacheable (no cache contexts). B is cacheable per role. If we
        //    don't have merging case 3, then A->orIf(B) will be globally cacheable,
        //    which means that even if a user of a different role logs in, the
        //    cached access result will be used, even though for that other role, B
        //    is forbidden!
        if ($this->is_forbidden() || $other->is_forbidden()) {
            $result = static::forbidden();
            if (!$this->is_forbidden() || $this->get_cache_max_age() === 0 && $other->is_forbidden()) {
                $merge_other = true;
            }
            if ($this->is_forbidden() && $this instanceof Access_Result_Reason_Interface && $this->get_reason() !== '') {
                $result->set_reason($this->get_reason());
            } elseif ($other->is_forbidden() && $other instanceof Access_Result_Reason_Interface && $other->get_reason() !== '') {
                $result->set_reason($other->get_reason());
            }
        } elseif ($this->is_allowed() || $other->is_allowed()) {
            $result = static::allowed();
            if (!$this->is_allowed() || $this->get_cache_max_age() === 0 && $other->is_allowed() || $this->get_cache_max_age() !== 0 && $other instanceof Cacheable_Dependency_Interface && $other->get_cache_max_age() !== 0) {
                $merge_other = true;
            }
        } else {
            $result = static::neutral();
            if ($this->get_cache_max_age() === 0 || $other instanceof Cacheable_Dependency_Interface && $other->get_cache_max_age() !== 0) {
                $merge_other = true;
            }
            if ($this instanceof Access_Result_Reason_Interface && $this->get_reason() !== '') {
                $result->set_reason($this->get_reason());
            } elseif ($other instanceof Access_Result_Reason_Interface && $other->get_reason() !== '') {
                $result->set_reason($other->get_reason());
            }
        }
        $result->inherit_cacheability($this);
        if ($merge_other) {
            $result->inherit_cacheability($other);
        }
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    public function and_if(Access_Result_Interface $other)
    {
        // The other access result's cacheability metadata is merged if $merge_other
        // gets set to TRUE. It gets set to TRUE in one case: if the other access
        // result is used.
        $merge_other = false;
        if ($this->is_forbidden() || $other->is_forbidden()) {
            $result = static::forbidden();
            if (!$this->is_forbidden()) {
                if ($other instanceof Access_Result_Reason_Interface) {
                    $result->set_reason($other->get_reason());
                }
                $merge_other = true;
            } else if ($this instanceof Access_Result_Reason_Interface) {
                $result->set_reason($this->get_reason());
            }
        } elseif ($this->is_allowed() && $other->is_allowed()) {
            $result = static::allowed();
            $merge_other = true;
        } else {
            $result = static::neutral();
            if (!$this->is_neutral()) {
                $merge_other = true;
                if ($other instanceof Access_Result_Reason_Interface) {
                    $result->set_reason($other->get_reason());
                }
            } else if ($this instanceof Access_Result_Reason_Interface) {
                $result->set_reason($this->get_reason());
            }
        }
        $result->inherit_cacheability($this);
        if ($merge_other) {
            $result->inherit_cacheability($other);
            // If this access result is not cacheable, then an AND with another access
            // result must also not be cacheable, except if the other access result
            // has isForbidden() === TRUE. isForbidden() access results are contagious
            // in that they propagate regardless of the other value.
            if ($this->get_cache_max_age() === 0 && !$result->is_forbidden()) {
                $result->set_cache_max_age(0);
            }
        }
        return $result;
    }
    /**
     * Inherits the cacheability of the other access result, if any.
     *
     * This method differs from addCacheableDependency() in how it handles
     * max-age, because it is designed to inherit the cacheability of the second
     * operand in the andIf() and orIf() operations. There, the situation
     * "allowed, max-age=0 OR allowed, max-age=1000" needs to yield max-age 1000
     * as the end result.
     *
     * @param \Drupal\Core\Access\AccessResultInterface $other
     *   The other access result, whose cacheability (if any) to inherit.
     *
     * @return $this
     */
    public function inherit_cacheability(Access_Result_Interface $other)
    {
        if ($other instanceof Cacheable_Dependency_Interface) {
            $this->add_cacheable_dependency($other);
            if ($this->get_cache_max_age() !== 0 && $other->get_cache_max_age() !== 0) {
                $this->set_cache_max_age(Cache::merge_max_ages($this->get_cache_max_age(), $other->get_cache_max_age()));
            } else {
                $this->set_cache_max_age($other->get_cache_max_age());
            }
        } else {
            $this->set_cache_max_age(0);
        }
        return $this;
    }
}