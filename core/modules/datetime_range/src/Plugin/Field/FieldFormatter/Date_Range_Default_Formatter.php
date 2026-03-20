<?php

declare(strict_types=1);

namespace Drupal\datetime_range\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldFormatter\DateTimeDefaultFormatter;
use Drupal\datetime_range\DateTimeRangeTrait;

/**
 * Plugin implementation of the 'Default' formatter for 'daterange' fields.
 *
 * This formatter renders the data range using <time> elements, with
 * configurable date formats (from the list of configured formats) and a
 * separator.
 */
#[FieldFormatter(
    id: 'daterange_default',
    label: new TranslatableMarkup('Default'),
    field_types: [
    'daterange',
  ],
)]
class DateRangeDefaultFormatter extends DateTimeDefaultFormatter
{
    use DateTimeRangeTrait;

    /**
     * {@inheritdoc}
     */
    public static function defaultSettings()
    {
        return static::dateTimeRangeDefaultSettings() + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state): array
    {
        $form = parent::settingsForm($form, $form_state);

        return $this->dateTimeRangeSettingsForm($form);
    }

    /**
     * {@inheritdoc}
     */
    public function settingsSummary(): array
    {
        return array_merge(parent::settingsSummary(), $this->dateTimeRangeSettingsSummary());
    }

}
