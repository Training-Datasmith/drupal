<?php

namespace Drupal\Core\TempStore;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Creates a PrivateTempStore object for a given collection.
 */
class PrivateTempStoreFactory {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a Drupal\Core\TempStore\PrivateTempStoreFactory object.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $storageFactory
   *   The key/value store factory.
   * @param \Drupal\Core\Lock\LockBackendInterface $lockBackend
   *   The lock object used for this data.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current account.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param int $expire
   *   The time to live for items, in seconds.
   */
  public function __construct(protected \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $storageFactory, protected \Drupal\Core\Lock\LockBackendInterface $lockBackend, protected \Drupal\Core\Session\AccountProxyInterface $currentUser, RequestStack $request_stack, /**
   * The time to live for items in seconds.
   */
  protected $expire = 604800) {
    $this->requestStack = $request_stack;
  }

  /**
   * Creates a PrivateTempStore.
   *
   * @param string $collection
   *   The collection name to use for this key/value store. This is typically
   *   a shared namespace or module name, e.g. 'views', 'entity', etc.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStore
   *   An instance of the key/value store.
   */
  public function get($collection): \Drupal\Core\TempStore\PrivateTempStore {
    // Store the data for this collection in the database.
    $storage = $this->storageFactory->get("tempstore.private.$collection");
    return new PrivateTempStore($storage, $this->lockBackend, $this->currentUser, $this->requestStack, $this->expire);
  }

}
