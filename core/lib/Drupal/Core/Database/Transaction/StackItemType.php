<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Transaction;

/**
 * Enumeration of the types of items in the Drupal transaction stack.
 */
enum Stack_Item_Type
{
    case Root;
    case Savepoint;
}