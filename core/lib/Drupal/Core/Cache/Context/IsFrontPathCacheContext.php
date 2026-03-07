<?php

declare(strict_types=1);

namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Defines a cache context for whether the URL is the front page of the site.
 *
 * Cache context ID: 'url.path.is_front'.
 */
class IsFrontPathCacheContext implements CacheContextInterface
{
    /**
     * Constructs an IsFrontPathCacheContext object.
     *
     * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
     *   The path matcher.
     */
    public function __construct(protected \Drupal\Core\Path\PathMatcherInterface $pathMatcher)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function getLabel()
    {
        return t('Is front page');
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): string
    {
        return 'is_front.' . (int) $this->pathMatcher->isFrontPage();
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheableMetadata(): \Drupal\Core\Cache\CacheableMetadata
    {
        $metadata = new CacheableMetadata();
        $metadata->addCacheTags(['config:system.site']);
        return $metadata;
    }

}
