<?php

declare (strict_types=1);
namespace Drupal\Component\Utility;

use Symfony\Component\Validator\Context\Execution_Context_Interface;
/**
 * Provides helper methods for byte conversions.
 */
class Bytes
{
    /**
     * The number of bytes in a kilobyte.
     *
     * @see http://wikipedia.org/wiki/Kilobyte
     */
    public const KILOBYTE = 1024;
    /**
     * The allowed suffixes of a bytes string in lowercase.
     *
     * @see http://wikipedia.org/wiki/Kilobyte
     */
    public const ALLOWED_SUFFIXES = ['', 'b', 'byte', 'bytes', 'k', 'kb', 'kilobyte', 'kilobytes', 'm', 'mb', 'megabyte', 'megabytes', 'g', 'gb', 'gigabyte', 'gigabytes', 't', 'tb', 'terabyte', 'terabytes', 'p', 'pb', 'petabyte', 'petabytes', 'e', 'eb', 'exabyte', 'exabytes', 'z', 'zb', 'zettabyte', 'zettabytes', 'y', 'yb', 'yottabyte', 'yottabytes'];
    /**
     * Parses a given byte size.
     *
     * @param int|float|string $size
     *   An integer, float, or string size expressed as a number of bytes with
     *   optional SI or IEC binary unit prefix (e.g. 2, 2.4, 3K, 5MB, 10G, 6GiB,
     *   8 bytes, 9mbytes).
     *
     * @return float
     *   The floating point value of the size in bytes.
     */
    public static function to_number($size): float
    {
        // Remove the non-unit characters from the size.
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        // Remove the non-numeric characters from the size.
        $size = preg_replace('/[^0-9\.]/', '', $size);
        if ($unit) {
            // Find the position of the unit in the ordered string which is the power
            // of magnitude to multiply a kilobyte by.
            return round($size * self::KILOBYTE ** stripos('bkmgtpezy', $unit[0]));
        }
        // Ensure size is a proper number type.
        return round((float) $size);
    }
    /**
     * Validate that a string is a representation of a number of bytes.
     *
     * @param string $string
     *   The string to validate.
     *
     * @return bool
     *   TRUE if the string is valid, FALSE otherwise.
     */
    public static function validate($string): bool
    {
        // Ensure that the string starts with a numeric character.
        if (!preg_match('/^[0-9]/', $string)) {
            return false;
        }
        // Remove the numeric characters from the beginning of the value.
        $string = preg_replace('/^[0-9\.]+/', '', $string);
        // Remove remaining spaces from the value.
        $string = trim((string) $string);
        return in_array(strtolower($string), self::ALLOWED_SUFFIXES);
    }
    /**
     * Validates a string is a representation of a number of bytes.
     *
     * To be used with the `Callback` constraint.
     *
     * @param string|int|float|null $value
     *   The string, integer or float to validate.
     * @param \Symfony\Component\Validator\Context\ExecutionContextInterface $context
     *   The validation execution context.
     *
     * @see \Symfony\Component\Validator\Constraints\CallbackValidator
     * @see core/config/schema/core.data_types.schema.yml
     */
    public static function validate_constraint(string|int|float|null $value, Execution_Context_Interface $context): void
    {
        // Ignore NULL values (i.e. support `nullable: true`).
        if ($value === null) {
            return;
        }
        if (!self::validate((string) $value)) {
            $context->add_violation('This value must be a number of bytes, optionally with a unit such as "MB" or "megabytes". %value does not represent a number of bytes.', ['%value' => $value]);
        }
    }
}