<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines the IsSuperUserCacheContext service, for "super user or not" caching.
 *
 * Cache context ID: 'user.is_super_user'.
 */
class Is_Super_User_Cache_Context extends User_Cache_Context_Base implements Cache_Context_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Is super user');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context(): string
    {
        return (int) $this->user->id() === 1 ? '1' : '0';
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata(): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}