<?php

declare(strict_types=1);

namespace Drupal\Component\Plugin\Exception;

/**
 * Thrown when a decorator's _call() method uses a method that does not exist.
 */
class InvalidDecoratedMethod extends \BadMethodCallException implements ExceptionInterface
{
}
