<?php

declare(strict_types=1);

namespace Drupal\Core\Field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'uri_link' formatter.
 */
#[FieldFormatter(
    id: 'uri_link',
    label: new TranslatableMarkup('Link to URI'),
    field_types: [
    'uri',
  ],
)]
class UriLinkFormatter extends FormatterBase
{
    /**
     * {@inheritdoc}
     * @return array{'#type': 'link', '#url': mixed, '#title': mixed}[]
     */
    public function viewElements(FieldItemListInterface $items, $langcode): array
    {
        $elements = [];

        foreach ($items as $delta => $item) {
            if (!$item->isEmpty()) {
                $elements[$delta] = [
                  '#type' => 'link',
                  '#url' => Url::fromUri($item->value),
                  '#title' => $item->value,
                ];
            }
        }

        return $elements;
    }

}
