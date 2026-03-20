<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * Exception thrown if server refuses connection.
 */
class Database_Connection_Refused_Exception extends \RuntimeException implements Database_Exception
{
}