<?php

declare (strict_types=1);
namespace Drupal\Component\Utility;

use Masterminds\HTML5\Serializer\Output_Rules;
// cspell:ignore drupalhtmlbuilder
/**
 * Drupal-specific HTML5 serializer rules.
 *
 * Drupal's XSS filtering cannot handle entities inside element attribute
 * values. The XSS filtering was written based on W3C XML recommendations
 * which constituted that the ampersand character (&) and the angle
 * brackets (< and >) must not appear in their literal form in attribute
 * values. This differs from the HTML living standard which permits angle
 * brackets.
 *
 * @see core/modules/ckeditor5/js/ckeditor5_plugins/drupalHtmlEngine/src/drupalhtmlbuilder.js
 */
class Html_Serializer_Rules extends Output_Rules
{
    /**
     * {@inheritdoc}
     */
    protected function escape($text, $attribute = false)
    {
        $text = parent::escape($text, $attribute);
        if ($attribute) {
            return strtr($text, ['<' => '&lt;', '>' => '&gt;']);
        }
        return $text;
    }
}