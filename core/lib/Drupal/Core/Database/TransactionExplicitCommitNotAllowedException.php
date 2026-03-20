<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * Exception to deny attempts to explicitly manage transactions.
 *
 * This exception will be thrown when the client connection commit() is called.
 * Code should never call this method directly.
 */
class Transaction_Explicit_Commit_Not_Allowed_Exception extends Transaction_Exception implements Database_Exception
{
}