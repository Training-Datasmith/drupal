<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

/**
 * Value object for a forbidden access result, with cacheability metadata.
 */
class Access_Result_Forbidden extends Access_Result implements Access_Result_Reason_Interface
{
    /**
     * Constructs a new AccessResultForbidden instance.
     *
     * @param null|string $reason
     *   (optional) A message to provide details about this access result.
     */
    public function __construct(
        /**
         * The reason why access is forbidden. For use in error messages.
         */
        protected $reason = null
    )
    {
    }
    /**
     * {@inheritdoc}
     */
    public function is_forbidden(): bool
    {
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function get_reason(): string
    {
        return (string) $this->reason;
    }
    /**
     * {@inheritdoc}
     */
    public function set_reason($reason): static
    {
        $this->reason = $reason;
        return $this;
    }
}