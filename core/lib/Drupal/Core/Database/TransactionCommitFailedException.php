<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Exception thrown when a commit() function fails.
 */
class TransactionCommitFailedException extends TransactionException implements DatabaseException
{
}
