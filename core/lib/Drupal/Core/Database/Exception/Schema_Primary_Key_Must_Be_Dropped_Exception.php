<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Exception;

use Drupal\Core\Database\Database_Exception;
use Drupal\Core\Database\Schema_Exception;
/**
 * Exception thrown if the Primary Key must be dropped before an operation.
 */
class Schema_Primary_Key_Must_Be_Dropped_Exception extends Schema_Exception implements Database_Exception
{
}