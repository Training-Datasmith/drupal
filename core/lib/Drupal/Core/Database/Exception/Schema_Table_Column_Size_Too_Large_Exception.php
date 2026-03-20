<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Exception;

use Drupal\Core\Database\Database_Exception;
use Drupal\Core\Database\Schema_Exception;
/**
 * Exception thrown if a column size is too large on table creation.
 */
class Schema_Table_Column_Size_Too_Large_Exception extends Schema_Exception implements Database_Exception
{
}