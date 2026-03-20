<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines the UrlCacheContext service, for "per page" caching.
 *
 * Cache context ID: 'url'.
 */
class Url_Cache_Context extends Request_Stack_Cache_Context_Base implements Cache_Context_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('URL');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context()
    {
        return $this->request_stack->get_current_request()->get_uri();
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata(): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}