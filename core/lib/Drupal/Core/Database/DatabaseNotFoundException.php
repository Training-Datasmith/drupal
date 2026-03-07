<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Exception thrown if specified database is not found.
 */
class DatabaseNotFoundException extends \RuntimeException implements DatabaseException
{
}
