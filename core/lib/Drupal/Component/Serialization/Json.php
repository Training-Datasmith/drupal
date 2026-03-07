<?php

declare(strict_types=1);

namespace Drupal\Component\Serialization;

/**
 * Default serialization for JSON.
 *
 * @ingroup third_party
 */
class Json implements SerializationInterface
{
    /**
     * {@inheritdoc}
     *
     * Uses HTML-safe strings, with several characters escaped.
     */
    public static function encode($variable)
    {
        // Encode <, >, ', &, and ".
        return json_encode($variable, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    }

    /**
     * {@inheritdoc}
     */
    public static function decode($string): mixed
    {
        return json_decode($string, true);
    }

    /**
     * {@inheritdoc}
     */
    public static function getFileExtension(): string
    {
        return 'json';
    }

}
