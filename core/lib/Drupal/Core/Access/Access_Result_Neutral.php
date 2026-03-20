<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

/**
 * Value object indicating a neutral access result, with cacheability metadata.
 */
class Access_Result_Neutral extends Access_Result implements Access_Result_Reason_Interface
{
    /**
     * Constructs a new AccessResultNeutral instance.
     *
     * @param null|string $reason
     *   (optional) A message to provide details about this access result.
     */
    public function __construct(
        /**
         * The reason why access is neutral. For use in messages.
         */
        protected $reason = null
    )
    {
    }
    /**
     * {@inheritdoc}
     */
    public function is_neutral(): bool
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