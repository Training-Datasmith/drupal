<?php

declare (strict_types=1);
namespace Drupal\Core\Datetime;

use Drupal\Core\String_Translation\Translatable_Markup;
/**
 * Helper class for dealing with timezones.
 */
class Time_Zone_Form_Helper
{
    /**
     * Generate an array of time zones names.
     *
     * This method retrieves the list of IANA time zones names that PHP is
     * configured to use, for display to users. It does not return the backward
     * compatible names (i.e., the ones defined in the back-zone file).
     *
     * @param bool $blank
     *   (optional) If TRUE, prepend an empty time zone option to the array.
     *
     * @return array
     *   An array or nested array containing time zones, keyed by the system name.
     *   The keys are valid time zone identifiers provided by
     *   \DateTimeZone::listIdentifiers()
     */
    public static function get_options_list(bool $blank = false): array
    {
        $zone_list = \DateTimeZone::list_identifiers();
        $zones = $blank ? ['' => new Translatable_Markup('- None selected -')] : [];
        foreach ($zone_list as $zone) {
            // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
            $zones[$zone] = new Translatable_Markup(str_replace('_', ' ', $zone));
        }
        // Sort the translated time zones alphabetically.
        asort($zones);
        return $zones;
    }
    /**
     * Generate an array of time zones names grouped by region.
     *
     * This method retrieves the list of IANA time zones names that PHP is
     * configured to use, for display to users. It does not return the backward
     * compatible names (i.e., the ones defined in the back-zone file).
     *
     * @param bool $blank
     *   (optional) If TRUE, prepend an empty time zone option to the array.
     *
     * @return array
     *   An nested array containing time zones, keyed by the system name. The keys
     *   are valid time zone identifiers provided by
     *   \DateTimeZone::listIdentifiers()
     */
    public static function get_options_list_by_region(bool $blank = false): array
    {
        $zones = static::get_options_list($blank);
        $grouped_zones = [];
        foreach ($zones as $key => $value) {
            $split = explode('/', (string) $value);
            $city = array_pop($split);
            $region = array_shift($split);
            if (!empty($region)) {
                $grouped_zones[$region][$key] = empty($split) ? $city : $city . ' (' . implode('/', $split) . ')';
            } else {
                $grouped_zones[$key] = $value;
            }
        }
        foreach ($grouped_zones as $key => $value) {
            if (is_array($grouped_zones[$key])) {
                asort($grouped_zones[$key]);
            }
        }
        return $grouped_zones;
    }
}