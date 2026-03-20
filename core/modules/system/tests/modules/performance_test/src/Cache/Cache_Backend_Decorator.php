<?php

declare(strict_types=1);

namespace Drupal\performance_test\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\performance_test\PerformanceDataCollector;

/**
 * Wraps an existing cache backend to track calls to the cache backend.
 */
class CacheBackendDecorator implements CacheBackendInterface, CacheTagsInvalidatorInterface
{
    public function __construct(protected readonly PerformanceDataCollector $performanceDataCollector, protected readonly CacheBackendInterface $cacheBackend, protected readonly string $bin)
    {
    }

    /**
     * Logs a cache operation.
     *
     * @param string|array $cids
     *   The cache IDs.
     * @param float $start
     *   The start microtime.
     * @param float $stop
     *   The stop microtime.
     * @param string $operation
     *   The type of operation being logged.
     */
    protected function logCacheOperation(string|array $cids, float $start, float $stop, string $operation): void
    {
        $this->performanceDataCollector->addCacheOperation([
          'operation' => $operation,
          'cids' => implode(', ', (array) $cids),
          'bin' => $this->bin,
          'start' => $start,
          'stop' => $stop,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function get($cid, $allow_invalid = false): object|bool
    {
        $start = microtime(true);
        $cache = $this->cacheBackend->get($cid, $allow_invalid);
        $stop = microtime(true);
        $this->logCacheOperation($cid, $start, $stop, 'get');
        return $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(&$cids, $allow_invalid = false): array
    {
        $cids_copy = $cids;
        $start = microtime(true);
        $cache = $this->cacheBackend->getMultiple($cids, $allow_invalid);
        $stop = microtime(true);
        $this->logCacheOperation($cids_copy, $start, $stop, 'getMultiple');

        return $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = [])
    {
        $start = microtime(true);
        $this->cacheBackend->set($cid, $data, $expire, $tags);
        $stop = microtime(true);
        $this->logCacheOperation($cid, $start, $stop, 'set');
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $items)
    {
        $cids = array_keys($items);
        $start = microtime(true);
        $this->cacheBackend->setMultiple($items);
        $stop = microtime(true);
        $this->logCacheOperation($cids, $start, $stop, 'setMultiple');
    }

    /**
     * {@inheritdoc}
     */
    public function delete($cid)
    {
        $start = microtime(true);
        $this->cacheBackend->delete($cid);
        $stop = microtime(true);
        $this->logCacheOperation($cid, $start, $stop, 'delete');
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $cids)
    {
        $start = microtime(true);
        $this->cacheBackend->deleteMultiple($cids);
        $stop = microtime(true);
        $this->logCacheOperation($cids, $start, $stop, 'deleteMultiple');
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll()
    {
        $start = microtime(true);
        $this->cacheBackend->deleteAll();
        $stop = microtime(true);
        $this->logCacheOperation([], $start, $stop, 'deleteAll');
    }

    /**
     * {@inheritdoc}
     */
    public function invalidate($cid)
    {
        $start = microtime(true);
        $this->cacheBackend->invalidate($cid);
        $stop = microtime(true);
        $this->logCacheOperation($cid, $start, $stop, 'invalidate');
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateMultiple(array $cids)
    {
        $start = microtime(true);
        $this->cacheBackend->invalidateMultiple($cids);
        $stop = microtime(true);
        $this->logCacheOperation($cids, $start, $stop, 'invalidateMultiple');
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateTags(array $tags)
    {
        if ($this->cacheBackend instanceof CacheTagsInvalidatorInterface) {
            $this->cacheBackend->invalidateTags($tags);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function garbageCollection()
    {
        $this->cacheBackend->garbageCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function removeBin()
    {
        $this->cacheBackend->removeBin();
    }

}
