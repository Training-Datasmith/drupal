<?php

declare (strict_types=1);
namespace Drupal\Core\Datetime;

use Drupal\Core\Config\Config_Factory_Interface;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\Language_Manager_Interface;
use Drupal\Core\String_Translation\String_Translation_Trait;
use Drupal\Core\String_Translation\Translation_Interface;
use Symfony\Component\Http_Foundation\Request_Stack;
/**
 * Provides a service to handle various date related functionality.
 *
 * @ingroup i18n
 */
class Date_Formatter implements Date_Formatter_Interface
{
    use String_Translation_Trait;
    /**
     * The list of loaded timezones.
     *
     * @var array
     */
    protected $timezones;
    /**
     * The available date formats.
     *
     * @var array
     */
    protected $date_formats = [];
    /**
     * Contains the different date interval units.
     *
     * This array is keyed by strings representing the unit (e.g.
     * '@count year|@count years') and with the amount of values of the unit in
     * seconds.
     *
     * @var array
     */
    protected $units = ['@count year|@count years' => 31536000, '@count month|@count months' => 2592000, '@count week|@count weeks' => 604800, '@count day|@count days' => 86400, '@count hour|@count hours' => 3600, '@count min|@count min' => 60, '@count sec|@count sec' => 1];
    public function __construct(protected Entity_Type_Manager_Interface $entity_type_manager, protected Language_Manager_Interface $language_manager, Translation_Interface $translation, protected Config_Factory_Interface $config_factory, protected Request_Stack $request_stack)
    {
        $this->string_translation = $translation;
    }
    /**
     * {@inheritdoc}
     */
    public function format($timestamp, $type = 'medium', $format = '', $timezone = null, $langcode = null)
    {
        if (!isset($timezone)) {
            $timezone = date_default_timezone_get();
        }
        // Store DateTimeZone objects in an array rather than repeatedly
        // constructing identical objects over the life of a request.
        if (!isset($this->timezones[$timezone])) {
            $this->timezones[$timezone] = timezone_open($timezone);
        }
        if (empty($langcode)) {
            $langcode = $this->language_manager->get_current_language()->get_id();
        }
        // Create a DrupalDateTime object from the timestamp and timezone.
        $create_settings = ['langcode' => $langcode];
        $date = Drupal_Date_Time::create_from_timestamp($timestamp, $this->timezones[$timezone], $create_settings);
        // If we have a non-custom date format use the provided date format pattern.
        if ($type && $type !== 'custom') {
            if ($date_format = $this->date_format($type, $langcode)) {
                $format = $date_format->get_pattern();
            }
        }
        // Fall back to the 'fallback' date format type if the format string is
        // empty, either from not finding a requested date format or being given an
        // empty custom format string.
        if (empty($format)) {
            $format = $this->date_format('fallback', $langcode)->get_pattern();
        }
        // Call $date->format().
        $settings = ['langcode' => $langcode];
        return $date->format($format, $settings);
    }
    /**
     * {@inheritdoc}
     */
    public function format_interval($interval, $granularity = 2, $langcode = null)
    {
        $output = '';
        foreach ($this->units as $key => $value) {
            $key = explode('|', (string) $key);
            if ($interval >= $value) {
                $output .= ($output ? ' ' : '') . $this->format_plural(floor($interval / $value), $key[0], $key[1], [], ['langcode' => $langcode]);
                $interval %= $value;
                $granularity--;
            } elseif ($output) {
                // Break if there was previous output but not any output at this level,
                // to avoid skipping levels and getting output like "@count year @count
                // second".
                break;
            }
            if ($granularity == 0) {
                break;
            }
        }
        return $output ?: $this->t('0 sec', [], ['langcode' => $langcode]);
    }
    /**
     * {@inheritdoc}
     */
    public function get_sample_date_formats($langcode = null, $timestamp = null, $timezone = null): array
    {
        $timestamp = $timestamp ?: time();
        // All date format characters for the PHP date() function.
        // cspell:disable-next-line
        $date_chars = str_split('dDjlNSwzWFmMntLoYyaABgGhHisueIOPTZcrU');
        $date_elements = array_combine($date_chars, $date_chars);
        return array_map(fn(string $character) => $this->format($timestamp, 'custom', $character, $timezone, $langcode), $date_elements);
    }
    /**
     * {@inheritdoc}
     */
    public function format_time_diff_until($timestamp, $options = [])
    {
        $request_time = $this->request_stack->get_current_request()->server->get('REQUEST_TIME');
        return $this->format_diff($request_time, $timestamp, $options);
    }
    /**
     * {@inheritdoc}
     */
    public function format_time_diff_since($timestamp, $options = [])
    {
        $request_time = $this->request_stack->get_current_request()->server->get('REQUEST_TIME');
        return $this->format_diff($timestamp, $request_time, $options);
    }
    /**
     * {@inheritdoc}
     */
    public function format_diff($from, $to, $options = [])
    {
        $options += ['granularity' => 2, 'langcode' => null, 'strict' => true, 'return_as_object' => false];
        if ($options['strict'] && $from > $to) {
            $string = $this->t('0 seconds');
            if ($options['return_as_object']) {
                return new Formatted_Date_Diff($string, 0);
            }
            return $string;
        }
        $date_time_from = new \DateTime();
        $date_time_from->set_timestamp($from);
        $date_time_to = new \DateTime();
        $date_time_to->set_timestamp($to);
        $interval = $date_time_to->diff($date_time_from);
        $granularity = $options['granularity'];
        $output = '';
        // We loop over the keys provided by \DateInterval explicitly. Since we
        // don't take the "invert" property into account, the resulting output value
        // will always be positive.
        $max_age = 1.0E+99;
        foreach (['y', 'm', 'd', 'h', 'i', 's'] as $value) {
            if ($interval->{$value} > 0) {
                // Switch over the keys to call formatPlural() explicitly with literal
                // strings for all different possibilities.
                switch ($value) {
                    case 'y':
                        $interval_output = $this->format_plural($interval->y, '@count year', '@count years', [], ['langcode' => $options['langcode']]);
                        $max_age = min($max_age, 365 * 86400);
                        break;
                    case 'm':
                        $interval_output = $this->format_plural($interval->m, '@count month', '@count months', [], ['langcode' => $options['langcode']]);
                        $max_age = min($max_age, 30 * 86400);
                        break;
                    case 'd':
                        // \DateInterval doesn't support weeks, so we need to calculate them
                        // ourselves.
                        $interval_output = '';
                        $days = $interval->d;
                        $weeks = floor($days / 7);
                        if ($weeks) {
                            $interval_output .= $this->format_plural($weeks, '@count week', '@count weeks', [], ['langcode' => $options['langcode']]);
                            $days -= $weeks * 7;
                            $granularity--;
                            $max_age = min($max_age, 7 * 86400);
                        }
                        if ((!$output || $weeks > 0) && $granularity > 0 && $days > 0) {
                            $interval_output .= ($interval_output ? ' ' : '') . $this->format_plural($days, '@count day', '@count days', [], ['langcode' => $options['langcode']]);
                            $max_age = min($max_age, 86400);
                        } else {
                            // If we did not output days, set the granularity to 0 so that we
                            // will not output hours and get things like "@count week @count
                            // hour".
                            $granularity = 0;
                        }
                        break;
                    case 'h':
                        $interval_output = $this->format_plural($interval->h, '@count hour', '@count hours', [], ['langcode' => $options['langcode']]);
                        $max_age = min($max_age, 3600);
                        break;
                    case 'i':
                        $interval_output = $this->format_plural($interval->i, '@count minute', '@count minutes', [], ['langcode' => $options['langcode']]);
                        $max_age = min($max_age, 60);
                        break;
                    case 's':
                        $interval_output = $this->format_plural($interval->s, '@count second', '@count seconds', [], ['langcode' => $options['langcode']]);
                        $max_age = min($max_age, 1);
                        break;
                }
                $output .= ($output && $interval_output ? ' ' : '') . $interval_output;
                $granularity--;
            } elseif ($output) {
                // Break if there was previous output but not any output at this level,
                // to avoid skipping levels and getting output like "@count year @count
                // second".
                break;
            }
            if ($granularity <= 0) {
                break;
            }
        }
        if (empty($output)) {
            $output = $this->t('0 seconds');
            $max_age = 0;
        }
        if ($options['return_as_object']) {
            return new Formatted_Date_Diff($output, $max_age);
        }
        return $output;
    }
    /**
     * Loads the given format pattern for the given langcode.
     *
     * @param string $type
     *   The machine name of the date format type which is one of:
     *   - One of the built-in date format types: 'short', 'medium',
     *     'long', 'html_datetime', 'html_date', 'html_time',
     *     'html_yearless_date', 'html_week', 'html_month', 'html_year'.
     *   - The name of a date format type defined by a date format config entity.
     *   - The machine name of an administrator-defined date format type.
     *   - 'custom' for a custom date format type.
     * @param string $langcode
     *   The langcode of the language to use.
     *
     * @return \Drupal\Core\Datetime\DateFormatInterface|null
     *   The configuration entity for the date format in the given language for
     *   non-custom formats, NULL otherwise.
     */
    protected function date_format($type, $langcode)
    {
        if (!isset($this->date_formats[$type][$langcode])) {
            $original_language = $this->language_manager->get_config_override_language();
            $this->language_manager->set_config_override_language(new Language(['id' => $langcode]));
            $this->date_formats[$type][$langcode] = $this->entity_type_manager->get_storage('date_format')->load($type);
            $this->language_manager->set_config_override_language($original_language);
        }
        return $this->date_formats[$type][$langcode];
    }
}