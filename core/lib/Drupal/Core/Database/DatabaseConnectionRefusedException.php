<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Exception thrown if server refuses connection.
 */
class DatabaseConnectionRefusedException extends \RuntimeException implements DatabaseException
{
}
