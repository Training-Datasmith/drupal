<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines the SiteCacheContext service, for "per site" caching.
 *
 * Cache context ID: 'url.site'.
 *
 * A "site" is defined as the combination of URI scheme, domain name, port and
 * base path. It allows for varying between the *same* site being accessed via
 * different entry points. (Different sites in a multisite setup have separate
 * databases.) For example: https://example.com and http://www.example.com.
 *
 * @see \Symfony\Component\HttpFoundation\Request::getSchemeAndHttpHost()
 * @see \Symfony\Component\HttpFoundation\Request::getBaseUrl()
 */
class Site_Cache_Context extends Request_Stack_Cache_Context_Base implements Cache_Context_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Site');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context(): string
    {
        $request = $this->request_stack->get_current_request();
        return $request->get_scheme_and_http_host() . $request->get_base_url();
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata(): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}