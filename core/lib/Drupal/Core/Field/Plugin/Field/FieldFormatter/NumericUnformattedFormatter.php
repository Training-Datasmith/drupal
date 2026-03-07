<?php

declare(strict_types=1);

namespace Drupal\Core\Field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'number_unformatted' formatter.
 */
#[FieldFormatter(
    id: 'number_unformatted',
    label: new TranslatableMarkup('Unformatted'),
    field_types: [
    'integer',
    'decimal',
    'float',
  ],
)]
class NumericUnformattedFormatter extends FormatterBase
{
    /**
     * {@inheritdoc}
     * @return array{'#markup': mixed}[]
     */
    public function viewElements(FieldItemListInterface $items, $langcode): array
    {
        $elements = [];

        foreach ($items as $delta => $item) {
            $elements[$delta] = ['#markup' => $item->value];
        }

        return $elements;
    }

}
