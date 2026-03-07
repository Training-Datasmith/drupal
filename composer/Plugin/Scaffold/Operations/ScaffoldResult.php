<?php

namespace Drupal\Composer\Plugin\Scaffold\Operations;

use Drupal\Composer\Plugin\Scaffold\ScaffoldFilePath;

/**
 * Record the result of a scaffold operation.
 *
 * @internal
 */
class ScaffoldResult {

  /**
   * ScaffoldResult constructor.
   *
   * @param \Drupal\Composer\Plugin\Scaffold\ScaffoldFilePath $destination
   *   The path to the scaffold file that was processed.
   * @param bool $managed
   *   (optional) Whether this result is managed. Defaults to FALSE.
   */
  public function __construct(
      protected \Drupal\Composer\Plugin\Scaffold\ScaffoldFilePath $destination,
      /**
       * Indicates if this scaffold file is managed by the scaffold command.
       */
      protected $managed = FALSE
  )
  {
  }

  /**
   * Determines whether this scaffold file is managed.
   *
   * @return bool
   *   TRUE if this scaffold file is managed, FALSE if not.
   */
  public function isManaged() {
    return $this->managed;
  }

  /**
   * Gets the destination scaffold file that this result refers to.
   *
   * @return \Drupal\Composer\Plugin\Scaffold\ScaffoldFilePath
   *   The destination path for the scaffold result.
   */
  public function destination() {
    return $this->destination;
  }

}
