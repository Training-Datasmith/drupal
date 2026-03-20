<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Component\Utility\Crypt;
/**
 * Defines the SessionCacheContext service, for "per session" caching.
 *
 * Cache context ID: 'session'.
 */
class Session_Cache_Context extends Request_Stack_Cache_Context_Base
{
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Session');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context(): string
    {
        return Crypt::hash_base64($this->request_stack->get_session()->get_id());
    }
}