<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

/**
 * AJAX command interface.
 *
 * All AJAX commands passed to AjaxResponse objects should implement these
 * methods.
 *
 * @ingroup ajax
 */
interface Command_Interface
{
    /**
     * Return an array to be run through json_encode and sent to the client.
     */
    public function render();
}