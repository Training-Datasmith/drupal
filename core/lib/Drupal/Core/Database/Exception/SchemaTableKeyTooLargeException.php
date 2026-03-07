<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Exception;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\SchemaException;

/**
 * Exception thrown if a key is too large.
 */
class SchemaTableKeyTooLargeException extends SchemaException implements DatabaseException
{
}
