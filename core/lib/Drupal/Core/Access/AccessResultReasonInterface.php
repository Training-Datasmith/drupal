<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

/**
 * Interface for access result value objects with stored reason for developers.
 *
 * For example, a developer can specify the reason for forbidden access:
 * @code
 * new AccessResultForbidden('You are not authorized to hack core');
 * @endcode
 *
 * @see \Drupal\Core\Access\AccessResultInterface
 */
interface Access_Result_Reason_Interface extends Access_Result_Interface
{
    /**
     * Gets the reason for this access result.
     *
     * @return string
     *   The reason of this access result or an empty string if no reason is
     *   provided.
     */
    public function get_reason();
    /**
     * Sets the reason for this access result.
     *
     * @param string|null $reason
     *   The reason of this access result or NULL if no reason is provided.
     *
     * @return \Drupal\Core\Access\AccessResultInterface
     *   The access result instance.
     */
    public function set_reason($reason);
}