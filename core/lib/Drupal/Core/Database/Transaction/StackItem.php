<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Transaction;

/**
 * A value object for items on the transaction stack.
 */
final readonly class Stack_Item
{
    /**
     * Constructor.
     *
     * @param string $name
     *   The name of the transaction.
     * @param StackItemType $type
     *   The stack item type.
     */
    public function __construct(public string $name, public Stack_Item_Type $type)
    {
    }
}