<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines the CookiesCacheContext service, for "per cookie" caching.
 *
 * Cache context ID: 'cookies' (to vary by all cookies).
 * Calculated cache context ID: 'cookies:%name', e.g. 'cookies:device_type' (to
 * vary by the 'device_type' cookie).
 */
class Cookies_Cache_Context extends Request_Stack_Cache_Context_Base implements Calculated_Cache_Context_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('HTTP cookies');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context($cookie = null)
    {
        if ($cookie === null) {
            $cookies = $this->request_stack->get_current_request()->cookies->all();
            // Sort the cookies by names, to always set the same context if the
            // cookies are the same but in a different order.
            ksort($cookies);
            // Use http_build_query() to get a short string from the cookies array.
            return http_build_query($cookies);
        }
        return $this->request_stack->get_current_request()->cookies->get($cookie);
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata($cookie = null): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}