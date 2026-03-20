<?php

declare(strict_types=1);

namespace Drupal\text\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'text_default' formatter.
 */
#[FieldFormatter(
    id: 'text_default',
    label: new TranslatableMarkup('Default'),
    field_types: [
    'text',
    'text_long',
    'text_with_summary',
  ],
)]
class TextDefaultFormatter extends FormatterBase
{
    /**
     * {@inheritdoc}
     * @return array{'#type': 'processed_text', '#text': mixed, '#format': mixed, '#langcode': mixed}[]
     */
    public function viewElements(FieldItemListInterface $items, $langcode): array
    {
        $elements = [];

        // The ProcessedText element already handles cache context & tag bubbling.
        // @see \Drupal\filter\Element\ProcessedText::preRenderText()
        foreach ($items as $delta => $item) {
            $elements[$delta] = [
              '#type' => 'processed_text',
              '#text' => $item->value,
              '#format' => $item->format,
              '#langcode' => $item->getLangcode(),
            ];
        }

        return $elements;
    }

}
