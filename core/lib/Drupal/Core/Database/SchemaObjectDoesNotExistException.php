<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * Exception thrown if an object being modified doesn't exist yet.
 *
 * For example, this exception should be thrown whenever there is an attempt to
 * modify a database table, field, or index that does not currently exist in
 * the database schema.
 */
class Schema_Object_Does_Not_Exist_Exception extends Schema_Exception implements Database_Exception
{
}