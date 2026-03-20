<?php

declare (strict_types=1);
namespace Drupal\Component\Render;

use Drupal\Component\Utility\Html;
/**
 * Provides an output strategy for transforming HTML into simple plain text.
 *
 * Use this when rendering a given HTML string into a plain text string that
 * does not need special formatting, such as a label or an email subject.
 *
 * Returns a string with HTML tags stripped and HTML entities decoded suitable
 * for email or other non-HTML contexts.
 */
class Plain_Text_Output implements Output_Strategy_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function render_from_html($string): string
    {
        return Html::decode_entities(strip_tags((string) $string));
    }
}