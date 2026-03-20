<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

/**
 * Defines an AJAX command that closes the currently visible modal dialog.
 *
 * @ingroup ajax
 */
class Close_Modal_Dialog_Command extends Close_Dialog_Command
{
    /**
     * Constructs a CloseModalDialogCommand object.
     *
     * @param bool $persist
     *   (optional) Whether to persist the dialog in the DOM or not.
     */
    public function __construct($persist = false)
    {
        $this->selector = '#drupal-modal';
        $this->persist = $persist;
    }
}