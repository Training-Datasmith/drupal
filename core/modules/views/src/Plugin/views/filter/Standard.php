<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;

/**
 * Default implementation of the base filter plugin.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter('standard')]
class Standard extends FilterPluginBase
{
}
