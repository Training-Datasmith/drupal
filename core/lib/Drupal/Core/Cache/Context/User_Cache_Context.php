<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines the UserCacheContext service, for "per user" caching.
 *
 * Cache context ID: 'user'.
 */
class User_Cache_Context extends User_Cache_Context_Base implements Cache_Context_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('User');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context()
    {
        return $this->user->id();
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata(): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}