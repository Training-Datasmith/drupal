<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

/**
 * The managed storage defers all the storage method calls to the manager.
 *
 * The reason for deferring all the method calls is that the storage interface
 * is the API but we potentially need to do an expensive transformation before
 * the storage can be used so we can't do it in the constructor but we also
 * don't know which method is called first.
 *
 * This class is not meant to be extended and is final to make sure the
 * assumptions that the storage is retrieved only once are upheld.
 */
final class Managed_Storage implements Storage_Interface
{
    /**
     * The decorated storage.
     *
     * @var \Drupal\Core\Config\StorageInterface
     */
    protected $storage;
    /**
     * ManagedStorage constructor.
     *
     * @param \Drupal\Core\Config\StorageManagerInterface $manager
     *   The storage manager.
     */
    public function __construct(
        /**
         * The storage manager to get the storage to decorate.
         */
        protected Storage_Manager_Interface $manager
    )
    {
    }
    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        return $this->get_storage()->exists($name);
    }
    /**
     * {@inheritdoc}
     */
    public function read($name)
    {
        return $this->get_storage()->read($name);
    }
    /**
     * {@inheritdoc}
     */
    public function read_multiple(array $names)
    {
        return $this->get_storage()->read_multiple($names);
    }
    /**
     * {@inheritdoc}
     */
    public function write($name, array $data)
    {
        return $this->get_storage()->write($name, $data);
    }
    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        return $this->get_storage()->delete($name);
    }
    /**
     * {@inheritdoc}
     */
    public function rename($name, $new_name)
    {
        return $this->get_storage()->rename($name, $new_name);
    }
    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        return $this->get_storage()->encode($data);
    }
    /**
     * {@inheritdoc}
     */
    public function decode($raw)
    {
        return $this->get_storage()->decode($raw);
    }
    /**
     * {@inheritdoc}
     */
    public function list_all($prefix = '')
    {
        return $this->get_storage()->list_all($prefix);
    }
    /**
     * {@inheritdoc}
     */
    public function delete_all($prefix = '')
    {
        return $this->get_storage()->delete_all($prefix);
    }
    /**
     * {@inheritdoc}
     */
    public function create_collection($collection)
    {
        // We return the collection directly.
        // This means that the collection will not be an instance of ManagedStorage
        // But this doesn't matter because the storage is retrieved from the
        // manager only the first time it is accessed.
        return $this->get_storage()->create_collection($collection);
    }
    /**
     * {@inheritdoc}
     */
    public function get_all_collection_names()
    {
        return $this->get_storage()->get_all_collection_names();
    }
    /**
     * {@inheritdoc}
     */
    public function get_collection_name()
    {
        return $this->get_storage()->get_collection_name();
    }
    /**
     * Get the decorated storage from the manager if necessary.
     *
     * @return \Drupal\Core\Config\StorageInterface
     *   The config storage.
     */
    protected function get_storage()
    {
        // Get the storage from the manager the first time it is needed.
        if (!isset($this->storage)) {
            $this->storage = $this->manager->get_storage();
        }
        return $this->storage;
    }
}