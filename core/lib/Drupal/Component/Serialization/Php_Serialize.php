<?php

declare (strict_types=1);
namespace Drupal\Component\Serialization;

/**
 * Default serialization for serialized PHP.
 */
class Php_Serialize implements Object_Aware_Serialization_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function encode($data): string
    {
        return serialize($data);
    }
    /**
     * {@inheritdoc}
     */
    public static function decode($raw): mixed
    {
        return unserialize($raw);
    }
    /**
     * {@inheritdoc}
     */
    public static function get_file_extension(): string
    {
        return 'serialized';
    }
}