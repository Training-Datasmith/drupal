<?php

declare(strict_types=1);

namespace Drupal\Core\Access;

/**
 * An exception thrown for access errors.
 *
 * Examples could be invalid access callback return values, or invalid access
 * objects being used.
 */
class AccessException extends \RuntimeException
{
}
