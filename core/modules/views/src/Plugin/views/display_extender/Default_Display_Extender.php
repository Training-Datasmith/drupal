<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\views\display_extender;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsDisplayExtender;

/**
 * Default display extender plugin; does nothing.
 *
 * @ingroup views_display_extender_plugins
 */
#[ViewsDisplayExtender(
    id: 'default',
    title: new TranslatableMarkup('Empty display extender'),
    help: new TranslatableMarkup('Default settings for this view.'),
    no_ui: true
)]
class DefaultDisplayExtender extends DisplayExtenderPluginBase
{
}
