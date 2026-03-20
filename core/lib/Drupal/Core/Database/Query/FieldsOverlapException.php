<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Database_Exception;
/**
 * Exception thrown if an insert query specifies a field twice.
 *
 * It is not allowed to specify a field as default and insert field, this
 * exception is thrown if that is the case.
 */
class Fields_Overlap_Exception extends \InvalidArgumentException implements Database_Exception
{
}