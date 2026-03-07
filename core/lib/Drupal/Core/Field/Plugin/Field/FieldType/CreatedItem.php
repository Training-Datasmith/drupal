<?php

declare(strict_types=1);

namespace Drupal\Core\Field\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the 'created' entity field type.
 */
#[FieldType(
    id: 'created',
    label: new TranslatableMarkup('Created'),
    description: new TranslatableMarkup('An entity field containing a UNIX timestamp of when the entity has been created.'),
    default_widget: 'datetime_timestamp',
    default_formatter: 'timestamp',
    no_ui: true,
    constraints: [
    'ComplexData' => [
      'properties' => [
        'value' => [
          'Range' => [
            'min' => '-2147483648',
            'max' => '2147483648',
          ],
        ],
      ],
    ],
  ]
)]
class CreatedItem extends TimestampItem
{
    /**
     * {@inheritdoc}
     */
    public function applyDefaultValue($notify = true): static
    {
        parent::applyDefaultValue($notify);
        // Created fields default to the current timestamp.
        $this->setValue(['value' => \Drupal::time()->getRequestTime()], $notify);
        return $this;
    }

}
