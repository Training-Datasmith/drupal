<?php

declare(strict_types=1);

namespace Drupal\menu_ui\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for menu_ui.
 */
class MenuUiThemeHooks
{
    /**
     * Implements hook_preprocess_HOOK() for block templates.
     */
    #[Hook('preprocess_block')]
    public function preprocessBlock(array &$variables): void
    {
        if ($variables['configuration']['provider'] == 'menu_ui') {
            $variables['attributes']['role'] = 'navigation';
        }
    }

}
