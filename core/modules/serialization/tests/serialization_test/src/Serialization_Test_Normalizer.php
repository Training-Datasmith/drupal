<?php

declare(strict_types=1);

namespace Drupal\serialization_test;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Serialization normalizer used for testing.
 */
class SerializationTestNormalizer implements NormalizerInterface
{
    /**
     * The format that this Normalizer supports.
     *
     * @var string
     */
    protected static $format = 'serialization_test';

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalized = (array) $object;
        // Add identifying value that can be used to verify that the expected
        // normalizer was invoked.
        $normalized['normalized_by'] = 'SerializationTestNormalizer';
        return $normalized;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return static::$format === $format;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
          \stdClass::class => true,
        ];
    }

}
