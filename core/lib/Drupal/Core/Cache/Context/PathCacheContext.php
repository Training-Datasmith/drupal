<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines the PathCacheContext service, for "per URL path" caching.
 *
 * Cache context ID: 'url.path'.
 *
 * (This allows for caching relative URLs.)
 *
 * @see \Symfony\Component\HttpFoundation\Request::getBasePath()
 * @see \Symfony\Component\HttpFoundation\Request::getPathInfo()
 */
class Path_Cache_Context extends Request_Stack_Cache_Context_Base implements Cache_Context_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Path');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context(): string
    {
        $request = $this->request_stack->get_current_request();
        return $request->get_base_path() . $request->get_path_info();
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata(): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}