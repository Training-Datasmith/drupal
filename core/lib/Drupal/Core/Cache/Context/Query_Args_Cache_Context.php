<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines the QueryArgsCacheContext service, for "per query args" caching.
 *
 * Cache context ID: 'url.query_args' (to vary by all query arguments).
 * Calculated cache context ID: 'url.query_args:%key', e.g.'url.query_args:foo'
 * (to vary by the 'foo' query argument).
 */
class Query_Args_Cache_Context extends Request_Stack_Cache_Context_Base implements Calculated_Cache_Context_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Query arguments');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context($query_arg = null)
    {
        if ($query_arg === null) {
            // All arguments requested. Use normalized query string to minimize
            // variations.
            $value = $this->request_stack->get_current_request()->get_query_string();
            return $value ?? '';
        }
        if ($this->request_stack->get_current_request()->query->has($query_arg)) {
            $value = $this->request_stack->get_current_request()->query->all()[$query_arg];
            if (is_array($value)) {
                return http_build_query($value);
            }
            if ($value !== '') {
                return $value;
            }
            return '?valueless?';
        }
        return '';
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata($query_arg = null): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}