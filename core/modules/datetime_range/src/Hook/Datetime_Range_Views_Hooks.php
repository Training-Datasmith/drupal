<?php

declare(strict_types=1);

namespace Drupal\datetime_range\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\datetime\DateTimeViewsHelper;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Hook implementations for datetime_range.
 */
class DatetimeRangeViewsHooks
{
    public function __construct(
        protected readonly DateTimeViewsHelper $dateTimeViewsHelper,
    ) {
    }

    /**
     * Implements hook_field_views_data().
     */
    #[Hook('field_views_data')]
    public function fieldViewsData(FieldStorageConfigInterface $field_storage): array
    {
        // Get datetime field data for value and end_value.
        $data = $this->dateTimeViewsHelper->buildViewsData($field_storage, [], 'value');
        return $this->dateTimeViewsHelper->buildViewsData($field_storage, $data, 'end_value');
    }

}
