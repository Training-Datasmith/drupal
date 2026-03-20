<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * This wrapper class serves only to provide additional debug information.
 *
 * This class will always wrap a client connection exception, for example
 * \PDOException or \mysqli_sql_exception.
 */
class Database_Exception_Wrapper extends \RuntimeException implements Database_Exception
{
}