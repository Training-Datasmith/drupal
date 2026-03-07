<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Exception;

use Drupal\Core\Database\DatabaseException;

/**
 * Exception thrown by the database event API.
 */
class EventException extends \RuntimeException implements DatabaseException
{
}
