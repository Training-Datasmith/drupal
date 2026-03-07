<?php

declare(strict_types=1);

namespace Drupal\datetime\Plugin\views\argument;

use Drupal\views\Attribute\ViewsArgument;

/**
 * Argument handler for a day.
 */
#[ViewsArgument(
    id: 'datetime_day',
)]
class DayDate extends Date
{
    /**
     * {@inheritdoc}
     */
    protected $argFormat = 'd';

}
