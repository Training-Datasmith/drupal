<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

/**
 * Provides an AJAX command for scrolling to the top of an element.
 *
 * This command is implemented in Drupal.AjaxCommands.prototype.scrollTop.
 */
class Scroll_Top_Command implements Command_Interface
{
    /**
     * Constructs a \Drupal\Core\Ajax\ScrollTopCommand object.
     *
     * @param string $selector
     *   A CSS selector.
     */
    public function __construct(
        /**
         * A CSS selector string.
         */
        protected $selector
    )
    {
    }
    /**
     * {@inheritdoc}
     */
    public function render(): array
    {
        return ['command' => 'scrollTop', 'selector' => $this->selector];
    }
}