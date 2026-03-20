<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * Exception for when popTransaction() is called with no active transaction.
 */
class Transaction_No_Active_Exception extends Transaction_Exception implements Database_Exception
{
}