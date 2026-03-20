<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Exception;

/**
 * An exception class thrown when contexts exist but are missing a value.
 */
class Missing_Value_Context_Exception extends Context_Exception
{
    /**
     * MissingValueContextException constructor.
     *
     * @param string[] $contexts_without_value
     *   List of contexts with missing value.
     */
    public function __construct(array $contexts_without_value = [])
    {
        $message = 'Required contexts without a value: ' . implode(', ', $contexts_without_value);
        parent::__construct($message);
    }
}