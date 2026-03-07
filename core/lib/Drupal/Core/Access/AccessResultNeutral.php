<?php

namespace Drupal\Core\Access;

/**
 * Value object indicating a neutral access result, with cacheability metadata.
 */
class AccessResultNeutral extends AccessResult implements AccessResultReasonInterface {

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
      protected $reason = NULL
  )
  {
  }

  /**
   * {@inheritdoc}
   */
  public function isNeutral(): bool {
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
