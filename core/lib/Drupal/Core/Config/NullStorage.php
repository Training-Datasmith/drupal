<?php

declare(strict_types=1);

namespace Drupal\Core\Config;

/**
 * Defines a stub storage.
 *
 * This storage is always empty; the controller reads and writes nothing.
 *
 * The stub implementation is needed for synchronizing configuration during
 * installation of a module, in which case all configuration being shipped with
 * the module is known to be new. Therefore, the module installation process is
 * able to short-circuit the full diff against the active configuration; the
 * diff would yield all currently available configuration as items to remove,
 * since they do not exist in the module's default configuration directory.
 *
 * This also can be used for testing purposes.
 */
class NullStorage implements StorageInterface
{
    /**
     * Constructs a new NullStorage.
     *
     * @param string $collection
     *   (optional) The collection to store configuration in. Defaults to the
     *   default collection.
     */
    public function __construct(
        /**
         * The storage collection.
         */
        protected $collection = StorageInterface::DEFAULT_COLLECTION
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function read($name): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function readMultiple(array $names): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function write($name, array $data): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function rename($name, $new_name): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function decode($raw)
    {
        return $raw;
    }

    /**
     * {@inheritdoc}
     */
    public function listAll($prefix = ''): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll($prefix = ''): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection($collection): static
    {
        return new static($collection);
    }

    /**
     * {@inheritdoc}
     */
    public function getAllCollectionNames(): array
    {
        // Returns only non empty collections.
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectionName()
    {
        return $this->collection;
    }

}
