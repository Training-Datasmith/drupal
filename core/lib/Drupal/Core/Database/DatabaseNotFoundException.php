<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * Exception thrown if specified database is not found.
 */
class Database_Not_Found_Exception extends \RuntimeException implements Database_Exception
{
}