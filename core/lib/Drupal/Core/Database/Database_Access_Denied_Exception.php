<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * Exception thrown if access credentials fail.
 */
class Database_Access_Denied_Exception extends \RuntimeException implements Database_Exception
{
}