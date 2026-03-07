<?php

declare(strict_types=1);

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;

/**
 * Provides a render element for an entire HTML page: <html> plus its children.
 */
#[RenderElement('html')]
class Html extends RenderElementBase
{
    /**
     * {@inheritdoc}
     */
    public function getInfo(): array
    {
        return [
          '#theme' => 'html',
        ];
    }

}
