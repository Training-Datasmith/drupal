<?php

declare(strict_types=1);

namespace Drupal\user\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'author' formatter.
 */
#[FieldFormatter(
    id: 'author',
    label: new TranslatableMarkup('Author'),
    description: new TranslatableMarkup('Display the referenced author user entity.'),
    field_types: [
    'entity_reference',
  ],
)]
class AuthorFormatter extends EntityReferenceFormatterBase
{
    /**
     * {@inheritdoc}
     * @return array{'#theme': 'username', '#account': mixed, '#link_options': array{attributes: array{rel: 'author'}}, '#cache': array{tags: mixed}}[]
     */
    public function viewElements(FieldItemListInterface $items, $langcode): array
    {
        $elements = [];

        foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
            $elements[$delta] = [
              '#theme' => 'username',
              '#account' => $entity,
              '#link_options' => ['attributes' => ['rel' => 'author']],
              '#cache' => [
                'tags' => $entity->getCacheTags(),
              ],
            ];
        }

        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public static function isApplicable(FieldDefinitionInterface $field_definition): bool
    {
        return $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'user';
    }

    /**
     * {@inheritdoc}
     */
    protected function checkAccess(EntityInterface $entity)
    {
        return $entity->access('view label', null, true);
    }

}
