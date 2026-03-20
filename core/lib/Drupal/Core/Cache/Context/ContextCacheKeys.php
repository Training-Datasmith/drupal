<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * A value object to store generated cache keys with its cacheability metadata.
 */
class Context_Cache_Keys extends Cacheable_Metadata
{
    /**
     * The generated cache keys.
     *
     * @var string[]
     */
    protected array $keys;
    /**
     * Constructs a ContextCacheKeys object.
     *
     * @param string[] $keys
     *   The cache context keys.
     */
    public function __construct(array $keys)
    {
        // Domain invariant: cache keys must be always sorted.
        // Sorting keys warrants that different combination of the same keys
        // generates the same cache cid.
        // @see \Drupal\Core\Render\RenderCache::createCacheID()
        sort($keys);
        $this->keys = $keys;
    }
    /**
     * Gets the generated cache keys.
     *
     * @return string[]
     *   The cache keys.
     */
    public function get_keys()
    {
        return $this->keys;
    }
}