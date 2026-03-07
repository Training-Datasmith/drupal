<?php

namespace Drupal\Core\Config;

/**
 * A ReadOnlyStorage decorates a storage and does not allow writing to it.
 */
class ReadOnlyStorage implements StorageInterface {

  /**
   * Create a ReadOnlyStorage decorating another storage.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The decorated storage.
   */
  public function __construct(protected \Drupal\Core\Config\StorageInterface $storage)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name) {
    return $this->storage->exists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    return $this->storage->read($name);
  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names) {
    return $this->storage->readMultiple($names);
  }

  /**
   * {@inheritdoc}
   */
  public function write($name, array $data): never {
    throw new \BadMethodCallException(__METHOD__ . ' is not allowed on a ReadOnlyStorage');
  }

  /**
   * {@inheritdoc}
   */
  public function delete($name): never {
    throw new \BadMethodCallException(__METHOD__ . ' is not allowed on a ReadOnlyStorage');
  }

  /**
   * {@inheritdoc}
   */
  public function rename($name, $new_name): never {
    throw new \BadMethodCallException(__METHOD__ . ' is not allowed on a ReadOnlyStorage');
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data) {
    return $this->storage->encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function decode($raw) {
    return $this->storage->decode($raw);
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    return $this->storage->listAll($prefix);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll($prefix = ''): never {
    throw new \BadMethodCallException(__METHOD__ . ' is not allowed on a ReadOnlyStorage');
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection($collection): static {
    return new static($this->storage->createCollection($collection));
  }

  /**
   * {@inheritdoc}
   */
  public function getAllCollectionNames() {
    return $this->storage->getAllCollectionNames();
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return $this->storage->getCollectionName();
  }

}
