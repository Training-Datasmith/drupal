<?php

declare(strict_types=1);

namespace Drupal\jsonapi\Normalizer;

use Drupal\jsonapi\JsonApiResource\Data;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;

/**
 * Normalizes JSON:API Data objects.
 *
 * @internal
 */
class DataNormalizer extends NormalizerBase
{
    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        assert($object instanceof Data);
        $cacheable_normalizations = array_map(fn (\Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface $resource) => $this->serializer->normalize($resource, $format, $context), $object->toArray());
        return $object->getCardinality() === 1
          ? array_shift($cacheable_normalizations) ?: CacheableNormalization::permanent(null)
          : CacheableNormalization::aggregate($cacheable_normalizations);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
          Data::class => true,
        ];
    }

}
