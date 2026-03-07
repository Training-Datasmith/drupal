<?php

namespace Drupal\Core\KeyValueStore;

/**
 * Defines the key/value store factory for the null backend.
 */
class KeyValueNullExpirableFactory implements KeyValueExpirableFactoryInterface {

  /**
   * {@inheritdoc}
   */
  public function get($collection): \Drupal\Core\KeyValueStore\NullStorageExpirable {
    return new NullStorageExpirable($collection);
  }

}
