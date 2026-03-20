<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * Exception thrown if no driver is specified for a database connection.
 */
class Driver_Not_Specified_Exception extends \RuntimeException
{
}