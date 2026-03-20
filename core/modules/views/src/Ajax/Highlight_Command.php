<?php

declare(strict_types=1);

namespace Drupal\views\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Provides an AJAX command for highlighting a certain new piece of html.
 *
 * This command is implemented in Drupal.AjaxCommands.prototype.viewsHighlight.
 */
class HighlightCommand implements CommandInterface
{
    /**
     * Constructs a \Drupal\views\Ajax\HighlightCommand object.
     *
     * @param string $selector
     *   A CSS selector.
     */
    public function __construct(
        /**
         * A CSS selector string.
         */
        protected $selector
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function render(): array
    {
        return [
          'command' => 'viewsHighlight',
          'selector' => $this->selector,
        ];
    }

}
