<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * Exception thrown when a savepoint or transaction name occurs twice.
 */
class Transaction_Name_Non_Unique_Exception extends Transaction_Exception implements Database_Exception
{
}