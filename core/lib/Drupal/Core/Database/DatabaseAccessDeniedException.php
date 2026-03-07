<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Exception thrown if access credentials fail.
 */
class DatabaseAccessDeniedException extends \RuntimeException implements DatabaseException
{
}
