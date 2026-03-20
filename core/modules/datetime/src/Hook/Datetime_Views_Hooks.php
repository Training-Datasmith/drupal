<?php

declare(strict_types=1);

namespace Drupal\datetime\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\datetime\DateTimeViewsHelper;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Hook implementations for datetime.
 */
class DatetimeViewsHooks
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
        return $this->dateTimeViewsHelper->buildViewsData($field_storage, [], $field_storage->getMainPropertyName());
    }

}
