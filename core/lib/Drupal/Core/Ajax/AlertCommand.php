<?php

declare(strict_types=1);

namespace Drupal\Core\Ajax;

/**
 * AJAX command for a javascript alert box.
 *
 * @ingroup ajax
 */
class AlertCommand implements CommandInterface
{
    /**
     * Constructs an AlertCommand object.
     *
     * @param string $text
     *   The text to be displayed in the alert box.
     */
    public function __construct(
        /**
         * The text to be displayed in the alert box.
         */
        protected $text
    ) {
    }

    /**
     * Implements Drupal\Core\Ajax\CommandInterface:render().
     */
    public function render(): array
    {

        return [
          'command' => 'alert',
          'text' => $this->text,
        ];
    }

}
