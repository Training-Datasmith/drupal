<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Database_Exception;
/**
 * Exception thrown if an insert query doesn't specify insert or default fields.
 */
class No_Fields_Exception extends \InvalidArgumentException implements Database_Exception
{
}