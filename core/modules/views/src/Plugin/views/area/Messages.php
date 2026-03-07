<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\views\area;

use Drupal\views\Attribute\ViewsArea;

/**
 * Provides an area for messages.
 *
 * @ingroup views_area_handlers
 */
#[ViewsArea('messages')]
class Messages extends AreaPluginBase
{
    /**
     * {@inheritdoc}
     */
    protected function defineOptions()
    {
        $options = parent::defineOptions();
        // Set the default to TRUE so it shows on empty pages by default.
        $options['empty']['default'] = true;
        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function render($empty = false): array
    {
        if (!$empty || !empty($this->options['empty'])) {
            return [
              '#type' => 'status_messages',
            ];
        }
        return [];
    }

}
