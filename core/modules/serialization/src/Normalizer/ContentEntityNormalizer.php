<?php

declare(strict_types=1);

namespace Drupal\serialization\Normalizer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\TypedData\TypedDataInternalPropertiesHelper;

/**
 * Normalizes/denormalizes Drupal content entities into an array structure.
 */
class ContentEntityNormalizer extends EntityNormalizer
{
    /**
     * {@inheritdoc}
     */
    public function normalize($entity, $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context += [
          'account' => null,
        ];

        $attributes = [];
        /** @var \Drupal\Core\Entity\Entity $entity */
        foreach (TypedDataInternalPropertiesHelper::getNonInternalProperties($entity->getTypedData()) as $name => $field_items) {
            if ($field_items->access('view', $context['account'])) {
                $attributes[$name] = $this->serializer->normalize($field_items, $format, $context);
            }
        }

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
          ContentEntityInterface::class => true,
        ];
    }

}
