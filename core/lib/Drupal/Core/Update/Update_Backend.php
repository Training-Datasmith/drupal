<?php

declare(strict_types=1);

namespace Drupal\Core\Update;

use Drupal\Core\Cache\NullBackend;

/**
 * Defines a cache backend for use during Drupal database updates.
 *
 * Passes on deletes to another backend while extending the NullBackend to avoid
 * using anything cached prior to running updates.
 */
class UpdateBackend extends NullBackend
{
    /**
     * UpdateBackend constructor.
     *
     * @param \Drupal\Core\Cache\CacheBackendInterface $backend
     *   The regular runtime cache backend.
     */
    public function __construct(protected \Drupal\Core\Cache\CacheBackendInterface $backend)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function delete($cid): void
    {
        $this->backend->delete($cid);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $cids): void
    {
        $this->backend->deleteMultiple($cids);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll(): void
    {
        $this->backend->deleteAll();
    }

}
