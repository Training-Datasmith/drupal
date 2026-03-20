<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

use Drupal\Core\Event_Subscriber\Main_Content_View_Subscriber;
/**
 * Provides a helper to determine if the current request is via AJAX.
 *
 * @internal
 */
trait Ajax_Helper_Trait
{
    /**
     * Determines if the current request is via AJAX.
     *
     * @return bool
     *   TRUE if the current request is via AJAX, FALSE otherwise.
     */
    protected function is_ajax(): bool
    {
        $wrapper_format = $this->get_request_wrapper_format() ?? '';
        return str_contains($wrapper_format, 'drupal_ajax') || str_contains($wrapper_format, 'drupal_modal') || str_contains($wrapper_format, 'drupal_dialog');
    }
    /**
     * Gets the wrapper format of the current request.
     *
     * @return string|null
     *   The wrapper format. NULL if the wrapper format is not set.
     */
    protected function get_request_wrapper_format()
    {
        return \Drupal::request()->query->get(Main_Content_View_Subscriber::WRAPPER_FORMAT);
    }
}