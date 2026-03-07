<?php

declare(strict_types=1);

namespace Drupal\datetime\Plugin\Field\FieldType;

/**
 * Interface definition for Datetime items.
 */
interface DateTimeItemInterface
{
    /**
     * Defines the timezone that dates should be stored in.
     */
    public const STORAGE_TIMEZONE = 'UTC';

    /**
     * Defines the format that date and time should be stored in.
     */
    public const DATETIME_STORAGE_FORMAT = 'Y-m-d\TH:i:s';

    /**
     * Defines the format that dates should be stored in.
     */
    public const DATE_STORAGE_FORMAT = 'Y-m-d';

}
