<?php

namespace Drupal\Composer\Plugin\Scaffold\Operations;

use Composer\IO\IOInterface;
use Drupal\Composer\Plugin\Scaffold\ScaffoldFilePath;
use Drupal\Composer\Plugin\Scaffold\ScaffoldOptions;

/**
 * Scaffold operation to skip a scaffold file (do nothing).
 *
 * @internal
 */
class SkipOp extends AbstractOperation {

  /**
   * Identifies Skip operations.
   */
  const ID = 'skip';

  /**
   * SkipOp constructor.
   *
   * @param string $message
   *   (optional) A custom message to output while skipping.
   */
  public function __construct(
      /**
       * The message to output while processing.
       */
      protected $message = "  - Skip <info>[dest-rel-path]</info>: disabled"
  )
  {
  }

  /**
   * {@inheritdoc}
   */
  protected function generateContents(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function process(ScaffoldFilePath $destination, IOInterface $io, ScaffoldOptions $options): \Drupal\Composer\Plugin\Scaffold\Operations\ScaffoldResult {
    $interpolator = $destination->getInterpolator();
    $io->write($interpolator->interpolate($this->message));
    return new ScaffoldResult($destination, FALSE);
  }

}
