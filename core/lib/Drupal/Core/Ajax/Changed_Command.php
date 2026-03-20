<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

/**
 * An AJAX command for marking HTML elements as changed.
 *
 * This command instructs the client to mark each of the elements matched by the
 * given selector as 'ajax-changed'.
 *
 * This command is implemented by Drupal.AjaxCommands.prototype.changed()
 * defined in misc/ajax.js.
 *
 * @ingroup ajax
 */
class Changed_Command implements Command_Interface
{
    /**
     * Constructs a ChangedCommand object.
     *
     * @param string $selector
     *   CSS selector for elements to be marked as changed.
     * @param string $asterisk
     *   CSS selector for elements to which an asterisk will be appended.
     */
    public function __construct(
        /**
         * A CSS selector string.
         *
         * If the command is a response to a request from an #ajax form element then
         * this value can be NULL.
         */
        protected $selector,
        /**
         * An optional CSS selector for elements to which asterisks will be appended.
         */
        protected $asterisk = ''
    )
    {
    }
    /**
     * Implements Drupal\Core\Ajax\CommandInterface:render().
     */
    public function render(): array
    {
        return ['command' => 'changed', 'selector' => $this->selector, 'asterisk' => $this->asterisk];
    }
}