<?php

declare(strict_types=1);

namespace Drupal\editor_test\EditorXssFilter;

use Drupal\editor\EditorXssFilterInterface;
use Drupal\filter\FilterFormatInterface;

/**
 * Defines an insecure text editor XSS filter (for testing purposes).
 */
class Insecure implements EditorXssFilterInterface
{
    /**
     * {@inheritdoc}
     */
    public static function filterXss($html, FilterFormatInterface $format, ?FilterFormatInterface $original_format = null)
    {
        // Don't apply any XSS filtering, just return the string we received.
        return $html;
    }

}
