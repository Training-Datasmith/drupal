<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * Base exception for Schema-related errors.
 */
class Schema_Exception extends \RuntimeException implements Database_Exception
{
}