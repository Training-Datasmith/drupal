<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Exception thrown if an undefined database connection is requested.
 */
class ConnectionNotDefinedException extends \RuntimeException
{
}
