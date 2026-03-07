<?php

namespace Drupal\Component\Serialization;

/**
 * Default serialization for serialized PHP.
 */
class PhpSerialize implements ObjectAwareSerializationInterface {

  /**
   * {@inheritdoc}
   */
  public static function encode($data): string {
    return serialize($data);
  }

  /**
   * {@inheritdoc}
   */
  public static function decode($raw): mixed {
    return unserialize($raw);
  }

  /**
   * {@inheritdoc}
   */
  public static function getFileExtension(): string {
    return 'serialized';
  }

}
