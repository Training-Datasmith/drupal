<?php

declare(strict_types=1);

namespace Drupal\views\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ViewsArgumentValidator attribute object for plugin discovery.
 *
 * Plugin Namespace: Plugin\ViewsArgumentValidator
 *
 * @see \Drupal\views\Plugin\views\argument_validator\ArgumentValidatorPluginBase
 *
 * @ingroup views_argument_validate_plugins
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ViewsArgumentValidator extends Plugin
{
    /**
     * Constructs a ViewsArgumentValidator attribute.
     *
     * @param string $id
     *   The plugin ID.
     * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $title
     *   The plugin title used in the views UI.
     * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $short_title
     *   (optional) The short title used in the views UI.
     * @param string|null $entity_type
     *   (optional) Entity type.
     * @param bool $no_ui
     *   (optional) Whether the plugin should be not selectable in the UI.
     *   If set to TRUE, you can still use it via the API in config files.
     *   Defaults to FALSE.
     * @param class-string|null $deriver
     *   (optional) The deriver class.
     */
    public function __construct(
        public readonly string $id,
        public readonly ?TranslatableMarkup $title = null,
        public readonly ?TranslatableMarkup $short_title = null,
        public readonly ?string $entity_type = null,
        public readonly bool $no_ui = false,
        public readonly ?string $deriver = null,
    ) {
    }

}
