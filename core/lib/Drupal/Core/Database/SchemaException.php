<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Base exception for Schema-related errors.
 */
class SchemaException extends \RuntimeException implements DatabaseException
{
}
