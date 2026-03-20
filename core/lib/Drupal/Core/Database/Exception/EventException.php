<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Exception;

use Drupal\Core\Database\Database_Exception;
/**
 * Exception thrown by the database event API.
 */
class Event_Exception extends \RuntimeException implements Database_Exception
{
}