<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines the TimeZoneCacheContext service, for "per time zone" caching.
 *
 * Cache context ID: 'timezone'.
 *
 * @see \Drupal\Core\Session\AccountProxy::setAccount()
 */
class Time_Zone_Cache_Context implements Cache_Context_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Time zone');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context(): string
    {
        // date_default_timezone_set() is called in AccountProxy::setAccount(), so
        // we can safely retrieve the timezone.
        return date_default_timezone_get();
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata(): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}