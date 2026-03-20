<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines the ProtocolVersionCacheContext service, for "per protocol" caching.
 *
 * Useful to differentiate between HTTP/1.1 and HTTP/2.0 responses for example,
 * to allow responses to be optimized for protocol-specific characteristics.
 *
 * Cache context ID: 'protocol_version'.
 */
class Protocol_Version_Cache_Context extends Request_Stack_Cache_Context_Base implements Cache_Context_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Protocol version');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context()
    {
        return $this->request_stack->get_current_request()->get_protocol_version();
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata(): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}