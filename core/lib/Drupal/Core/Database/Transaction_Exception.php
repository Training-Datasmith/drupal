<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * Exception thrown by an error in a database transaction.
 */
class Transaction_Exception extends \RuntimeException implements Database_Exception
{
}