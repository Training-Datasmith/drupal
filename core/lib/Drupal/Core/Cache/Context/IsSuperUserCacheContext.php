<?php

declare(strict_types=1);

namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Defines the IsSuperUserCacheContext service, for "super user or not" caching.
 *
 * Cache context ID: 'user.is_super_user'.
 */
class IsSuperUserCacheContext extends UserCacheContextBase implements CacheContextInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getLabel()
    {
        return t('Is super user');
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): string
    {
        return ((int) $this->user->id()) === 1 ? '1' : '0';
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheableMetadata(): \Drupal\Core\Cache\CacheableMetadata
    {
        return new CacheableMetadata();
    }

}
