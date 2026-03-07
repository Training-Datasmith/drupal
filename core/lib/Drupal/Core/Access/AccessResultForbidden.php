<?php

namespace Drupal\Core\Access;

/**
 * Value object for a forbidden access result, with cacheability metadata.
 */
class AccessResultForbidden extends AccessResult implements AccessResultReasonInterface {

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
      protected $reason = NULL
  )
  {
  }

  /**
   * {@inheritdoc}
   */
  public function isForbidden(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getReason(): string {
    return (string) $this->reason;
  }

  /**
   * {@inheritdoc}
   */
  public function setReason($reason): static {
    $this->reason = $reason;
    return $this;
  }

}
