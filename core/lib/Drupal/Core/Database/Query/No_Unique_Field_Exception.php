<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Database_Exception;
/**
 * Exception thrown if an upsert query doesn't specify a unique field.
 */
class No_Unique_Field_Exception extends \InvalidArgumentException implements Database_Exception
{
}