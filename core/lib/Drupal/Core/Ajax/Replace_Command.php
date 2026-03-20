<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

/**
 * AJAX command for calling the jQuery replace() method.
 *
 * The 'insert/replaceWith' command instructs the client to use jQuery's
 * replaceWith() method to replace each element matched by the given selector
 * with the given render array or HTML.
 *
 * This command is implemented by Drupal.AjaxCommands.prototype.insert()
 * defined in misc/ajax.js.
 *
 * See
 * @link http://docs.jquery.com/Manipulation/replaceWith#content jQuery replaceWith command @endlink
 *
 * @ingroup ajax
 */
class Replace_Command extends Insert_Command
{
    /**
     * Implements Drupal\Core\Ajax\CommandInterface:render().
     */
    public function render(): array
    {
        return ['command' => 'insert', 'method' => 'replaceWith', 'selector' => $this->selector, 'data' => $this->get_rendered_content(), 'settings' => $this->settings];
    }
}