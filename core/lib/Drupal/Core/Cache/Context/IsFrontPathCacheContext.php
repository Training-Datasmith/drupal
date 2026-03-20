<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines a cache context for whether the URL is the front page of the site.
 *
 * Cache context ID: 'url.path.is_front'.
 */
class Is_Front_Path_Cache_Context implements Cache_Context_Interface
{
    /**
     * Constructs an IsFrontPathCacheContext object.
     *
     * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
     *   The path matcher.
     */
    public function __construct(protected \Drupal\Core\Path\Path_Matcher_Interface $path_matcher)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Is front page');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context(): string
    {
        return 'is_front.' . (int) $this->path_matcher->is_front_page();
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata(): \Drupal\Core\Cache\Cacheable_Metadata
    {
        $metadata = new Cacheable_Metadata();
        $metadata->add_cache_tags(['config:system.site']);
        return $metadata;
    }
}