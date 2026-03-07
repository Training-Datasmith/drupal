<?php

declare(strict_types=1);

namespace Drupal\Core\Executable;

use Drupal\Component\Plugin\Exception\ExceptionInterface;

/**
 * Generic executable plugin exception class.
 */
class ExecutableException extends \Exception implements ExceptionInterface
{
}
