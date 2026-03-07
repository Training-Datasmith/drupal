<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\views\display;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsDisplay;

/**
 * The plugin that handles an embed display.
 *
 * @ingroup views_display_plugins
 *
 * @todo Wait until annotations/plugins support access methods.
 *   no_ui => !\Drupal::config('views.settings')->get('ui.show.display_embed'),
 */
#[ViewsDisplay(
    id: 'embed',
    title: new TranslatableMarkup('Embed'),
    help: new TranslatableMarkup('Provide a display which can be embedded using the views api.'),
    theme: 'views_view',
    uses_menu_links: false
)]
class Embed extends DisplayPluginBase
{
    /**
     * {@inheritdoc}
     */
    protected $usesAttachments = true;

    /**
     * {@inheritdoc}
     */
    public function buildRenderable(array $args = [], $cache = true)
    {
        $build = parent::buildRenderable($args, $cache);
        $build['#embed'] = true;
        return $build;
    }

}
