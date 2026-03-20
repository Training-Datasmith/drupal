<?php

declare (strict_types=1);
namespace Drupal\Core\Datetime\Element;

use Drupal\Component\Utility\Filter_Array;
use Drupal\Component\Utility\Nested_Array;
use Drupal\Component\Utility\Variable;
use Drupal\Core\Datetime\Date_Helper;
use Drupal\Core\Datetime\Drupal_Date_Time;
use Drupal\Core\Form\Form_State_Interface;
use Drupal\Core\Render\Attribute\Form_Element;
use Drupal\Core\Security\Do_Trusted_Callback_Trait;
use Drupal\Core\Security\Static_Trusted_Callback_Helper;
/**
 * Provides a datelist element.
 */
#[Form_Element('datelist')]
class Datelist extends Date_Element_Base
{
    use Do_Trusted_Callback_Trait;
    /**
     * {@inheritdoc}
     */
    public function get_info(): array
    {
        // Note that since this information is cached, the #date_timezone property
        // is not set here, as this needs to vary potentially by-user.
        return ['#input' => true, '#element_validate' => [[static::class, 'validateDatelist']], '#process' => [[static::class, 'processDatelist']], '#theme' => 'datetime_form', '#theme_wrappers' => ['datetime_wrapper'], '#date_part_order' => ['year', 'month', 'day', 'hour', 'minute'], '#date_year_range' => '1900:2050', '#date_increment' => 1, '#date_date_callbacks' => []];
    }
    /**
     * {@inheritdoc}
     *
     * Validates the date type to adjust 12 hour time and prevent invalid dates.
     * If the date is valid, the date is set in the form.
     */
    public static function value_callback(&$element, $input, Form_State_Interface $form_state)
    {
        $element += ['#date_timezone' => date_default_timezone_get()];
        $parts = $element['#date_part_order'];
        $increment = $element['#date_increment'];
        $date = null;
        if ($input !== false) {
            $return = $input;
            if (empty(static::check_empty_inputs($input, $parts))) {
                if (isset($input['ampm'])) {
                    if ($input['ampm'] == 'pm' && $input['hour'] < 12) {
                        $input['hour'] += 12;
                    } elseif ($input['ampm'] == 'am' && $input['hour'] == 12) {
                        $input['hour'] -= 12;
                    }
                    unset($input['ampm']);
                }
                try {
                    $date = Drupal_Date_Time::create_from_array($input, $element['#date_timezone']);
                } catch (\Exception) {
                    $form_state->set_error($element, t('Selected combination of day and month is not valid.'));
                }
                if ($date instanceof Drupal_Date_Time && !$date->has_errors()) {
                    static::increment_round($date, $increment);
                }
            }
        } else {
            $return = array_fill_keys($parts, '');
            if (!empty($element['#default_value'])) {
                $date = $element['#default_value'];
                if ($date instanceof Drupal_Date_Time && !$date->has_errors()) {
                    $date->set_timezone(new \DateTimeZone($element['#date_timezone']));
                    static::increment_round($date, $increment);
                    foreach ($parts as $part) {
                        $format = match ($part) {
                            'day' => 'j',
                            'month' => 'n',
                            'year' => 'Y',
                            'hour' => in_array('ampm', $element['#date_part_order']) ? 'g' : 'G',
                            'minute' => 'i',
                            'second' => 's',
                            'ampm' => 'a',
                            default => '',
                        };
                        $return[$part] = $date->format($format);
                    }
                }
            }
        }
        $return['object'] = $date;
        return $return;
    }
    /**
     * Expands a date element into an array of individual elements.
     *
     * Required settings:
     *   - #default_value: A DrupalDateTime object, adjusted to the proper local
     *     timezone. Converting a date stored in the database from UTC to the
     *     local zone and converting it back to UTC before storing it is not
     *     handled here. This element accepts a date as the default value, and
     *     then converts the user input strings back into a new date object on
     *     submission. No timezone adjustment is performed.
     * Optional properties include:
     *   - #date_part_order: Array of date parts indicating the parts and order
     *     that should be used in the selector, optionally including 'ampm' for
     *     12 hour time. Default is ['year', 'month', 'day', 'hour', 'minute'].
     *   - #date_text_parts: Array of date parts that should be presented as
     *     text fields instead of drop-down selectors. Default is an empty array.
     *   - #date_date_callbacks: Array of optional callbacks for the date element.
     *   - #date_year_range: A description of the range of years to allow, like
     *     '1900:2050', '-3:+3' or '2000:+3', where the first value describes the
     *     earliest year and the second the latest year in the range. A year
     *     in either position means that specific year. A +/- value describes a
     *     dynamic value that is that many years earlier or later than the current
     *     year at the time the form is displayed. Defaults to '1900:2050'.
     *   - #date_increment: The increment to use for minutes and seconds, i.e.
     *     '15' would show only :00, :15, :30 and :45. Defaults to 1 to show every
     *     minute.
     *   - #date_timezone: The Time Zone Identifier (TZID) to use when displaying
     *     or interpreting dates, i.e: 'Asia/Kolkata'. Defaults to the value
     *     returned by date_default_timezone_get().
     *
     * Example usage:
     * @code
     *   $form = [
     *     '#type' => 'datelist',
     *     '#default_value' => new DrupalDateTime('2000-01-01 00:00:00'),
     *     '#date_part_order' => ['month', 'day', 'year', 'hour', 'minute', 'ampm'],
     *     '#date_text_parts' => ['year'],
     *     '#date_year_range' => '2010:2020',
     *     '#date_increment' => 15,
     *     '#date_timezone' => 'Asia/Kolkata'
     *   ];
     * @endcode
     *
     * @param array $element
     *   The form element whose value is being processed.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     * @param array $complete_form
     *   The complete form structure.
     *
     * @return array
     *   An expanded DateList element.
     */
    public static function process_datelist(array &$element, Form_State_Interface $form_state, &$complete_form): array
    {
        // Load translated date part labels from the appropriate calendar plugin.
        $date_helper = new Date_Helper();
        // The value callback has populated the #value array.
        $date = !empty($element['#value']['object']) ? $element['#value']['object'] : null;
        $element['#tree'] = true;
        // Determine the order of the date elements.
        $order = !empty($element['#date_part_order']) ? $element['#date_part_order'] : ['year', 'month', 'day'];
        $text_parts = !empty($element['#date_text_parts']) ? $element['#date_text_parts'] : [];
        // Output multi-selector for date.
        foreach ($order as $part) {
            switch ($part) {
                case 'day':
                    $options = $date_helper->days($element['#required']);
                    $format = 'j';
                    $title = t('Day');
                    break;
                case 'month':
                    $options = $date_helper->month_names_abbr($element['#required']);
                    $format = 'n';
                    $title = t('Month');
                    break;
                case 'year':
                    $range = static::datetime_range_years($element['#date_year_range'], $date);
                    $options = $date_helper->years($range[0], $range[1], $element['#required']);
                    $format = 'Y';
                    $title = t('Year');
                    break;
                case 'hour':
                    $format = in_array('ampm', $element['#date_part_order']) ? 'g' : 'G';
                    $options = $date_helper->hours($format, $element['#required']);
                    $title = t('Hour');
                    break;
                case 'minute':
                    $format = 'i';
                    $options = $date_helper->minutes($format, $element['#required'], $element['#date_increment']);
                    $title = t('Minute');
                    break;
                case 'second':
                    $format = 's';
                    $options = $date_helper->seconds($format, $element['#required'], $element['#date_increment']);
                    $title = t('Second');
                    break;
                case 'ampm':
                    $format = 'a';
                    $options = $date_helper->ampm($element['#required']);
                    $title = t('AM/PM');
                    break;
                default:
                    $format = '';
                    $options = [];
                    $title = '';
            }
            $default = isset($element['#value'][$part]) && trim($element['#value'][$part]) != '' ? $element['#value'][$part] : '';
            $value = $date instanceof Drupal_Date_Time && !$date->has_errors() ? $date->format($format) : $default;
            if (!empty($value) && $part != 'ampm') {
                $value = intval($value);
            }
            $element['#attributes']['title'] = $title;
            $element[$part] = ['#type' => in_array($part, $text_parts) ? 'textfield' : 'select', '#title' => $title, '#value' => $value, '#attributes' => $element['#attributes'], '#options' => $options, '#required' => $element['#required'], '#error_no_message' => false, '#empty_option' => $title];
        }
        // Allows custom callbacks to alter the element.
        if (!empty($element['#date_date_callbacks'])) {
            foreach ($element['#date_date_callbacks'] as $callback) {
                $message = sprintf('Datelist element #date_date_callbacks callbacks must be methods of a class that implements \Drupal\Core\Security\TrustedCallbackInterface or be an anonymous function. The callback was %s. See https://www.drupal.org/node/3217966', Variable::callable_to_string($callback));
                Static_Trusted_Callback_Helper::callback($callback, [&$element, $form_state, $date], $message);
            }
        }
        return $element;
    }
    /**
     * Validation callback for a datelist element.
     *
     * If the date is valid, the date object created from the user input is set in
     * the form for use by the caller. The work of compiling the user input back
     * into a date object is handled by the value callback, so we can use it here.
     * We also have the raw input available for validation testing.
     *
     * @param array $element
     *   The element being processed.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     * @param array $complete_form
     *   The complete form structure.
     */
    public static function validate_datelist(array &$element, Form_State_Interface $form_state, array &$complete_form): void
    {
        $input_exists = false;
        $input = Nested_Array::get_value($form_state->get_values(), $element['#parents'], $input_exists);
        $title = static::get_element_title($element, $complete_form);
        if ($input_exists) {
            $all_empty = static::check_empty_inputs($input, $element['#date_part_order']);
            // If there's empty input and the field is not required, set it to empty.
            if (empty($input['year']) && empty($input['month']) && empty($input['day']) && !$element['#required']) {
                $form_state->set_value_for_element($element, null);
            } elseif (empty($input['year']) && empty($input['month']) && empty($input['day']) && $element['#required']) {
                $form_state->set_error($element, t('The %field date is required.', ['%field' => $title]));
            } elseif (!empty($all_empty)) {
                foreach ($all_empty as $value) {
                    $form_state->set_error($element, t('The %field date is incomplete.', ['%field' => $title]));
                    $form_state->set_error($element[$value], t('A value must be selected for %part.', ['%part' => $value]));
                }
            } else {
                // If the input is valid, set it.
                $date = $input['object'];
                if ($date instanceof Drupal_Date_Time && !$date->has_errors()) {
                    $form_state->set_value_for_element($element, $date);
                } elseif ($form_state->get_error($element) === null) {
                    $form_state->set_error($element, t('The %field date is invalid.', ['%field' => $title]));
                }
            }
        }
    }
    /**
     * Checks the input array for empty values.
     *
     * Input array keys are checked against values in the parts array. Elements
     * not in the parts array are ignored. Returns an array representing elements
     * from the input array that have no value. If no empty values are found,
     * returned array is empty.
     *
     * @param array $input
     *   Array of individual inputs to check for value.
     * @param array $parts
     *   Array to check input against, ignoring elements not in this array.
     *
     * @return array
     *   Array of keys from the input array that have no value, may be empty.
     */
    protected static function check_empty_inputs(array $input, $parts): array
    {
        // The object key does not represent an input value, see
        // \Drupal\Core\Datetime\Element\Datelist::valueCallback().
        unset($input['object']);
        // Filters out empty array values, any valid value would have a string
        // length.
        $filtered_input = Filter_Array::remove_empty_strings($input);
        return array_diff($parts, array_keys($filtered_input));
    }
    /**
     * Rounds minutes and seconds to nearest requested value.
     *
     * @param mixed $date
     *   The date.
     * @param int $increment
     *   The value to round to.
     *
     * @return \Drupal\Core\Datetime\DrupalDateTime
     *   The Drupal date time object with the minutes and seconds rounded when the
     *   input date is a DrupalDateTime instance. Otherwise the date is returned
     *   unchanged.
     */
    protected static function increment_round(&$date, $increment)
    {
        // Round minutes and seconds, if necessary.
        if ($date instanceof Drupal_Date_Time && $increment > 1) {
            $day = intval($date->format('j'));
            $hour = intval($date->format('H'));
            $second = intval(round(intval($date->format('s')) / $increment) * $increment);
            $minute = intval($date->format('i'));
            if ($second == 60) {
                $minute += 1;
                $second = 0;
            }
            $minute = intval(round($minute / $increment) * $increment);
            if ($minute == 60) {
                $hour += 1;
                $minute = 0;
            }
            $date->set_time($hour, $minute, $second);
            if ($hour == 24) {
                $day += 1;
                $year = $date->format('Y');
                $month = $date->format('n');
                $date->set_date($year, $month, $day);
            }
        }
        return $date;
    }
}