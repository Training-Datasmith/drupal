<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines a cache context service for path parents.
 *
 * Cache context ID: 'url.path.parent'.
 *
 * This allows for caching based on the path, excluding everything after the
 * last forward slash.
 */
class Path_Parent_Cache_Context extends Request_Stack_Cache_Context_Base implements Cache_Context_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Parent path');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context(): string
    {
        $request = $this->request_stack->get_current_request();
        $path_elements = explode('/', trim((string) $request->get_path_info(), '/'));
        array_pop($path_elements);
        return implode('/', $path_elements);
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata(): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}