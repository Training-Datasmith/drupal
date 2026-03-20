<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * Exception thrown transactions are out of order.
 *
 * This is thrown when a rollBack() resulted in other active transactions being
 * rolled-back.
 */
class Transaction_Out_Of_Order_Exception extends Transaction_Exception implements Database_Exception
{
}