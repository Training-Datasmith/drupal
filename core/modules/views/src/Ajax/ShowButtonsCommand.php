<?php

declare(strict_types=1);

namespace Drupal\views\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Provides an AJAX command for showing the save and cancel buttons.
 *
 * This command is implemented in
 * Drupal.AjaxCommands.prototype.viewsShowButtons.
 */
class ShowButtonsCommand implements CommandInterface
{
    /**
     * Constructs a \Drupal\views\Ajax\ShowButtonsCommand object.
     *
     * @param bool $changed
     *   Whether the view has been changed.
     */
    public function __construct(
        /**
         * Whether the view has been changed.
         */
        protected $changed
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function render(): array
    {
        return [
          'command' => 'viewsShowButtons',
          'changed' => $this->changed,
        ];
    }

}
