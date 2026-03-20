<?php

declare(strict_types=1);

namespace Drupal\Core\Field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'entity reference ID' formatter.
 */
#[FieldFormatter(
    id: 'entity_reference_entity_id',
    label: new TranslatableMarkup('Entity ID'),
    description: new TranslatableMarkup('Display the ID of the referenced entities.'),
    field_types: [
    'entity_reference',
  ],
)]
class EntityReferenceIdFormatter extends EntityReferenceFormatterBase
{
    /**
     * {@inheritdoc}
     * @return array{'#plain_text': mixed, '#cache': array{tags: mixed}}[]
     */
    public function viewElements(FieldItemListInterface $items, $langcode): array
    {
        $elements = [];

        foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
            if ($entity->id()) {
                $elements[$delta] = [
                  '#plain_text' => $entity->id(),
                  // Create a cache tag entry for the referenced entity. In the case
                  // that the referenced entity is deleted, the cache for referring
                  // entities must be cleared.
                  '#cache' => [
                    'tags' => $entity->getCacheTags(),
                  ],
                ];
            }
        }

        return $elements;
    }

}
