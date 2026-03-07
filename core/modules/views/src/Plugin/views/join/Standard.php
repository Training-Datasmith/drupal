<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\views\join;

use Drupal\views\Attribute\ViewsJoin;

/**
 * Default implementation of the join plugin.
 *
 * @ingroup views_join_handlers
 */
#[ViewsJoin('standard')]
class Standard extends JoinPluginBase
{
}
