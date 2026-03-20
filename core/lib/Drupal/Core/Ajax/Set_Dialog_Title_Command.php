<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

/**
 * Defines an AJAX command that sets jQuery UI dialog properties.
 *
 * @ingroup ajax
 */
class Set_Dialog_Title_Command extends Set_Dialog_Option_Command
{
    /**
     * Constructs a SetDialogTitleCommand object.
     *
     * @param string $selector
     *   The selector of the dialog whose title will be set. If set to an empty
     *   value, the default modal dialog will be selected.
     * @param string $title
     *   The title that will be set on the dialog.
     */
    public function __construct($selector, $title)
    {
        $this->selector = $selector ?: '#drupal-modal';
        $this->option_name = 'title';
        $this->option_value = $title;
    }
}