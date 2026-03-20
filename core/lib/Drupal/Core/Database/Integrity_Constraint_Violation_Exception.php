<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * Exception thrown if a query would violate an integrity constraint.
 *
 * This exception is thrown e.g. when trying to insert a row that would violate
 * a unique key constraint.
 */
class Integrity_Constraint_Violation_Exception extends \RuntimeException implements Database_Exception
{
}