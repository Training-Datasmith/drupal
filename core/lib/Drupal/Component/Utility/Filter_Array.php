<?php

declare (strict_types=1);
namespace Drupal\Component\Utility;

/**
 * Provides methods to filter arrays.
 *
 * @ingroup utility
 */
class Filter_Array
{
    /**
     * Removes empty strings from an array.
     *
     * This method removes all empty strings from the input array. This is
     * particularly useful to preserve 0 whilst filtering other falsy values. The
     * values are first cast to a string before comparison.
     *
     * @param array $value
     *   The array to filter.
     *
     * @return array
     *   The filtered array.
     */
    public static function remove_empty_strings(array $value): array
    {
        return array_filter($value, static fn($item): bool => (string) $item !== '');
    }
}