<?php

declare(strict_types=1);

namespace Drupal\search\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Search type attribute for plugin discovery.
 *
 * Search classes define search types for the core Search module. Each search
 * type can be used to create search pages from the Search settings page.
 *
 * @see SearchPluginBase
 *
 * @ingroup search
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Search extends Plugin
{
    /**
     * Constructs a Search attribute.
     *
     * @param string $id
     *   The plugin ID.
     * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $title
     *   The title for the search page tab.
     * @param bool $use_admin_theme
     *   Whether search results should be displayed in admin theme or not.
     * @param class-string|null $deriver
     *   (optional) The deriver class.
     */
    public function __construct(
        public readonly string $id,
        public readonly ?TranslatableMarkup $title = null,
        public readonly bool $use_admin_theme = false,
        public readonly ?string $deriver = null,
    ) {
    }

}
