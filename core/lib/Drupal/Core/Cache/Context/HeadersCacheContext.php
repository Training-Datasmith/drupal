<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines the HeadersCacheContext service, for "per header" caching.
 *
 * Cache context ID: 'headers' (to vary by all headers).
 * Calculated cache context ID: 'headers:%name', e.g. 'headers:X-Something' (to
 * vary by the 'X-Something' header).
 */
class Headers_Cache_Context extends Request_Stack_Cache_Context_Base implements Calculated_Cache_Context_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('HTTP headers');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context($header = null)
    {
        if ($header === null) {
            $headers = $this->request_stack->get_current_request()->headers->all();
            // Order headers by name to have less cache variations.
            ksort($headers);
            $result = '';
            foreach ($headers as $name => $value) {
                if ($result) {
                    $result .= '&';
                }
                // Sort values to minimize cache variations.
                sort($value);
                $result .= $name . '=' . implode(',', $value);
            }
            return $result;
        }
        if ($this->request_stack->get_current_request()->headers->has($header)) {
            $value = $this->request_stack->get_current_request()->headers->get($header);
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
    public function get_cacheable_metadata($header = null): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}