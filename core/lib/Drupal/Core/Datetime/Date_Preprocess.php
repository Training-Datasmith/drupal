<?php

declare (strict_types=1);
namespace Drupal\Core\Datetime;

use Drupal\Core\String_Translation\String_Translation_Trait;
use Drupal\Core\Template\Attribute;
/**
 * Preprocess for common/core theme templates.
 *
 * @internal
 */
class Date_Preprocess
{
    use String_Translation_Trait;
    public function __construct(protected Date_Formatter_Interface $date_formatter)
    {
    }
    /**
     * Prepares variables for time templates.
     *
     * Default template: time.html.twig.
     *
     * @param array $variables
     *   An associative array possibly containing:
     *   - "attributes['timestamp']:".
     *   - "timestamp:".
     *   - "text:".
     */
    public function preprocess_time(array &$variables): void
    {
        // Format the 'datetime' attribute based on the timestamp.
        // @see https://www.w3.org/TR/html5-author/the-time-element.html#attr-time-datetime
        if (!isset($variables['attributes']['datetime']) && isset($variables['timestamp'])) {
            $variables['attributes']['datetime'] = $this->date_formatter->format($variables['timestamp'], 'html_datetime', '', 'UTC');
        }
        // If no text was provided, try to auto-generate it.
        if (!isset($variables['text'])) {
            // Format and use a human-readable version of the timestamp, if any.
            if (isset($variables['timestamp'])) {
                $variables['text'] = $this->date_formatter->format($variables['timestamp']);
            } elseif (isset($variables['attributes']['datetime'])) {
                $variables['text'] = $variables['attributes']['datetime'];
            }
        }
    }
    /**
     * Prepares variables for datetime form element templates.
     *
     * The datetime form element serves as a wrapper around the date element type,
     * which creates a date and a time component for a date.
     *
     * Default template: datetime-form.html.twig.
     *
     * @param array $variables
     *   An associative array containing:
     *   - element: An associative array containing the properties of the element.
     *     Properties used: #title, #value, #options, #description, #required,
     *     #attributes.
     *
     * @see form_process_datetime()
     */
    public function preprocess_datetime_form(array &$variables): void
    {
        $element = $variables['element'];
        $variables['attributes'] = [];
        if (isset($element['#id'])) {
            $variables['attributes']['id'] = $element['#id'];
        }
        if (!empty($element['#attributes']['class'])) {
            $variables['attributes']['class'] = (array) $element['#attributes']['class'];
        }
        $variables['content'] = $element;
    }
    /**
     * Prepares variables for datetime form wrapper templates.
     *
     * Default template: datetime-wrapper.html.twig.
     *
     * @param array $variables
     *   An associative array containing:
     *   - element: An associative array containing the properties of the element.
     *     Properties used: #title, #children, #required, #attributes.
     */
    public function preprocess_datetime_wrapper(array &$variables): void
    {
        $element = $variables['element'];
        if (!empty($element['#title'])) {
            $variables['title'] = $element['#title'];
            // If the element title is a string, wrap it a render array so that markup
            // will not be escaped (but XSS-filtered).
            if (is_string($variables['title']) && $variables['title'] !== '') {
                $variables['title'] = ['#markup' => $variables['title']];
            }
        }
        // Suppress error messages.
        $variables['errors'] = null;
        $variables['description'] = null;
        if (!empty($element['#description'])) {
            $description_attributes = [];
            if (!empty($element['#id'])) {
                $description_attributes['id'] = $element['#id'] . '--description';
            }
            $description_attributes['data-drupal-field-elements'] = 'description';
            $variables['description'] = $element['#description'];
            $variables['description_attributes'] = new Attribute($description_attributes);
        }
        $variables['required'] = false;
        // For required datetime fields 'form-required' & 'js-form-required' classes
        // are appended to the label attributes.
        if (!empty($element['#required'])) {
            $variables['required'] = true;
        }
        $variables['content'] = $element['#children'];
    }
}