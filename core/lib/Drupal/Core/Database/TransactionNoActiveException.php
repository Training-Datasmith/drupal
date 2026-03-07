<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Exception for when popTransaction() is called with no active transaction.
 */
class TransactionNoActiveException extends TransactionException implements DatabaseException
{
}
