<?php

declare (strict_types=1);
namespace Drupal\Component\Utility;

/**
 * Provides generic array sorting helper methods.
 *
 * @ingroup utility
 */
class Sort_Array
{
    /**
     * Sorts a structured array by the 'weight' element.
     *
     * Note that the sorting is by the 'weight' array element, not by the render
     * element property '#weight'.
     *
     * Callback for uasort().
     *
     * @param array $a
     *   First item for comparison. The compared items should be associative
     *   arrays that optionally include a 'weight' element. For items without a
     *   'weight' element, a default value of 0 will be used.
     * @param array $b
     *   Second item for comparison.
     *
     * @return int
     *   The comparison result for uasort().
     */
    public static function sort_by_weight_element(array $a, array $b)
    {
        return static::sort_by_key_int($a, $b, 'weight');
    }
    /**
     * Sorts a structured array by '#weight' property.
     *
     * Callback for uasort().
     *
     * @param array $a
     *   First item for comparison. The compared items should be associative
     *   arrays that optionally include a '#weight' key.
     * @param array $b
     *   Second item for comparison.
     *
     * @return int
     *   The comparison result for uasort().
     */
    public static function sort_by_weight_property($a, $b)
    {
        return static::sort_by_key_int($a, $b, '#weight');
    }
    /**
     * Sorts a structured array by 'title' key (no # prefix).
     *
     * Callback for uasort().
     *
     * @param array $a
     *   First item for comparison. The compared items should be associative
     *   arrays that optionally include a 'title' key.
     * @param array $b
     *   Second item for comparison.
     *
     * @return int
     *   The comparison result for uasort().
     */
    public static function sort_by_title_element($a, $b)
    {
        return static::sort_by_key_string($a, $b, 'title');
    }
    /**
     * Sorts a structured array by '#title' property.
     *
     * Callback for uasort().
     *
     * @param array $a
     *   First item for comparison. The compared items should be associative
     *   arrays that optionally include a '#title' key.
     * @param array $b
     *   Second item for comparison.
     *
     * @return int
     *   The comparison result for uasort().
     */
    public static function sort_by_title_property($a, $b)
    {
        return static::sort_by_key_string($a, $b, '#title');
    }
    /**
     * Sorts a string array item by an arbitrary key.
     *
     * @param array $a
     *   First item for comparison.
     * @param array $b
     *   Second item for comparison.
     * @param string $key
     *   The key to use in the comparison.
     *
     * @return int
     *   The comparison result for uasort().
     */
    public static function sort_by_key_string($a, $b, $key): int
    {
        $a_title = is_array($a) && isset($a[$key]) ? $a[$key] : '';
        $b_title = is_array($b) && isset($b[$key]) ? $b[$key] : '';
        return strnatcasecmp((string) $a_title, (string) $b_title);
    }
    /**
     * Sorts an integer array item by an arbitrary key.
     *
     * @param array $a
     *   First item for comparison.
     * @param array $b
     *   Second item for comparison.
     * @param string $key
     *   The key to use in the comparison.
     *
     * @return int
     *   The comparison result for uasort().
     */
    public static function sort_by_key_int($a, $b, $key): int
    {
        $a_weight = is_array($a) && isset($a[$key]) ? $a[$key] : 0;
        $b_weight = is_array($b) && isset($b[$key]) ? $b[$key] : 0;
        return $a_weight <=> $b_weight;
    }
    /**
     * Sorts an array recursively, by key, alphabetically.
     *
     * @param array $data
     *   The array to sort, passed by reference.
     */
    public static function sort_by_key_recursive(array &$data): void
    {
        // If the array is a list, it is by definition already sorted.
        if (!array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as &$value) {
            if (is_array($value)) {
                self::sort_by_key_recursive($value);
            }
        }
    }
}