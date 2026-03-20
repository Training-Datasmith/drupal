<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

/**
 * A ReadOnlyStorage decorates a storage and does not allow writing to it.
 */
class Read_Only_Storage implements Storage_Interface
{
    /**
     * Create a ReadOnlyStorage decorating another storage.
     *
     * @param \Drupal\Core\Config\StorageInterface $storage
     *   The decorated storage.
     */
    public function __construct(protected \Drupal\Core\Config\Storage_Interface $storage)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        return $this->storage->exists($name);
    }
    /**
     * {@inheritdoc}
     */
    public function read($name)
    {
        return $this->storage->read($name);
    }
    /**
     * {@inheritdoc}
     */
    public function read_multiple(array $names)
    {
        return $this->storage->read_multiple($names);
    }
    /**
     * {@inheritdoc}
     */
    public function write($name, array $data): never
    {
        throw new \BadMethodCallException(__METHOD__ . ' is not allowed on a ReadOnlyStorage');
    }
    /**
     * {@inheritdoc}
     */
    public function delete($name): never
    {
        throw new \BadMethodCallException(__METHOD__ . ' is not allowed on a ReadOnlyStorage');
    }
    /**
     * {@inheritdoc}
     */
    public function rename($name, $new_name): never
    {
        throw new \BadMethodCallException(__METHOD__ . ' is not allowed on a ReadOnlyStorage');
    }
    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        return $this->storage->encode($data);
    }
    /**
     * {@inheritdoc}
     */
    public function decode($raw)
    {
        return $this->storage->decode($raw);
    }
    /**
     * {@inheritdoc}
     */
    public function list_all($prefix = '')
    {
        return $this->storage->list_all($prefix);
    }
    /**
     * {@inheritdoc}
     */
    public function delete_all($prefix = ''): never
    {
        throw new \BadMethodCallException(__METHOD__ . ' is not allowed on a ReadOnlyStorage');
    }
    /**
     * {@inheritdoc}
     */
    public function create_collection($collection): static
    {
        return new static($this->storage->create_collection($collection));
    }
    /**
     * {@inheritdoc}
     */
    public function get_all_collection_names()
    {
        return $this->storage->get_all_collection_names();
    }
    /**
     * {@inheritdoc}
     */
    public function get_collection_name()
    {
        return $this->storage->get_collection_name();
    }
}