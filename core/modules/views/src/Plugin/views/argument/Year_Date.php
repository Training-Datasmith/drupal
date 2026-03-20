<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\views\argument;

use Drupal\views\Attribute\ViewsArgument;

/**
 * Argument handler for a year (CCYY)
  */
#[ViewsArgument(
    id: 'date_year',
)]
class YearDate extends Date
{
    /**
     * {@inheritdoc}
     */
    protected $argFormat = 'Y';

}
