<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

/**
 * A trait for cache tag checksum implementations.
 *
 * Handles delayed cache tag invalidations.
 */
trait Cache_Tags_Checksum_Trait
{
    /**
     * A list of tags that have already been invalidated in this request.
     *
     * Used to prevent the invalidation of the same cache tag multiple times.
     *
     * @var bool[]
     */
    protected $invalidated_tags = [];
    /**
     * The set of cache tags whose invalidation is delayed.
     *
     * @var string[]
     */
    protected $delayed_tags = [];
    /**
     * Contains already loaded tag invalidation counts from the storage.
     *
     * @var int[]
     */
    protected $tag_cache = [];
    /**
     * Registered cache tags to preload.
     */
    protected array $preload_tags = [];
    /**
     * Callback to be invoked just after a database transaction gets committed.
     *
     * Executes all delayed tag invalidations.
     *
     * @param bool $success
     *   Whether or not the transaction was successful.
     */
    public function root_transaction_end_callback($success): void
    {
        if ($success) {
            $this->do_invalidate_tags($this->delayed_tags);
        }
        $this->delayed_tags = [];
    }
    /**
     * {@inheritdoc}
     */
    public function invalidate_tags(array $tags): void
    {
        foreach ($tags as $key => $tag) {
            if (isset($this->invalidated_tags[$tag])) {
                unset($tags[$key]);
            } else {
                $this->invalidated_tags[$tag] = true;
                unset($this->tag_cache[$tag]);
            }
        }
        if (!$tags) {
            return;
        }
        $in_transaction = $this->get_database_connection()->in_transaction();
        if ($in_transaction) {
            if (empty($this->delayed_tags)) {
                $this->get_database_connection()->transaction_manager()->add_post_transaction_callback([$this, 'rootTransactionEndCallback']);
            }
            $this->delayed_tags = Cache::merge_tags($this->delayed_tags, $tags);
        } else {
            $this->do_invalidate_tags($tags);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_current_checksum(array $tags)
    {
        // Any cache writes in this request containing cache tags whose invalidation
        // has been delayed due to an in-progress transaction must not be read by
        // any other request, so use a nonsensical checksum which will cause any
        // written cache items to be ignored.
        if (!empty(array_intersect($tags, $this->delayed_tags))) {
            return Cache_Tags_Checksum_Interface::INVALID_CHECKSUM_WHILE_IN_TRANSACTION;
        }
        // Remove tags that were already invalidated during this request from the
        // static caches so that another invalidation can occur later in the same
        // request. Without that, written cache items would not be invalidated
        // correctly.
        foreach ($tags as $tag) {
            unset($this->invalidated_tags[$tag]);
        }
        return $this->calculate_checksum($tags);
    }
    /**
     * Implements \Drupal\Core\Cache\CacheTagsChecksumInterface::isValid()
     */
    public function is_valid($checksum, array $tags)
    {
        // If there are no cache tags, then there is no cache tag to validate,
        // hence it's always valid.
        if (empty($tags)) {
            return true;
        }
        // Any cache reads in this request involving cache tags whose invalidation
        // has been delayed due to an in-progress transaction are not allowed to use
        // data stored in cache; it must be assumed to be stale. This forces those
        // results to be computed instead. Together with the logic in
        // ::getCurrentChecksum(), it also prevents that computed data from being
        // written to the cache.
        if (!empty(array_intersect($tags, $this->delayed_tags))) {
            return false;
        }
        return $checksum == $this->calculate_checksum($tags);
    }
    /**
     * Calculates the current checksum for a given set of tags.
     *
     * @param string[] $tags
     *   The array of tags to calculate the checksum for.
     *
     * @return int
     *   The calculated checksum.
     */
    protected function calculate_checksum(array $tags): int|float
    {
        $checksum = 0;
        // If there are no cache tags, then there is no cache tag to checksum,
        // so return early.
        if (empty($tags)) {
            return $checksum;
        }
        // If there are registered preload tags, add them to the tags list then
        // reset the list. This needs to make sure that it only returns the
        // requested cache tags, so store the combination of requested and
        // preload cache tags in a separate variable.
        $tags_with_preload = $tags;
        if ($this->preload_tags) {
            $tags_with_preload = array_unique(array_merge($tags, $this->preload_tags));
            $this->preload_tags = [];
        }
        $query_tags = array_diff($tags_with_preload, array_keys($this->tag_cache));
        if ($query_tags) {
            $tag_invalidations = $this->get_tag_invalidation_counts($query_tags);
            $this->tag_cache += $tag_invalidations;
            // Fill static cache with empty objects for tags not found in the storage.
            $this->tag_cache += array_fill_keys(array_diff($query_tags, array_keys($tag_invalidations)), 0);
        }
        foreach ($tags as $tag) {
            $checksum += $this->tag_cache[$tag];
        }
        return $checksum;
    }
    /**
     * Implements \Drupal\Core\Cache\CacheTagsChecksumInterface::reset()
     */
    public function reset(): void
    {
        $this->tag_cache = [];
        $this->invalidated_tags = [];
    }
    /**
     * Implements \Drupal\Core\Cache\CacheTagsChecksumPreloadInterface::registerCacheTagsForPreload()
     */
    public function register_cache_tags_for_preload(array $cache_tags): void
    {
        if (empty($cache_tags)) {
            return;
        }
        // Don't preload delayed tags that are awaiting invalidation.
        $preloadable_tags = array_diff($cache_tags, $this->delayed_tags);
        if ($preloadable_tags) {
            $this->preload_tags = array_merge($this->preload_tags, $preloadable_tags);
        }
    }
    /**
     * Fetches invalidation counts for cache tags.
     *
     * @param string[] $tags
     *   The list of tags to fetch invalidations for.
     *
     * @return int[]
     *   List of invalidation counts keyed by the respective cache tag.
     *
     * @throws \Exception
     *   Thrown if the table could not be created or the database connection
     *   failed.
     */
    abstract protected function get_tag_invalidation_counts(array $tags);
    /**
     * Returns the database connection.
     *
     * @return \Drupal\Core\Database\Connection
     *   The database connection.
     */
    abstract protected function get_database_connection();
    /**
     * Marks cache items with any of the specified tags as invalid.
     *
     * @param string[] $tags
     *   The set of tags for which to invalidate cache items.
     *
     * @throws \Exception
     *   Thrown if the table could not be created or the database connection
     *   failed.
     */
    abstract protected function do_invalidate_tags(array $tags);
}