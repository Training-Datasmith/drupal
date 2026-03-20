<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * Exception thrown when a commit() function fails.
 */
class Transaction_Commit_Failed_Exception extends Transaction_Exception implements Database_Exception
{
}