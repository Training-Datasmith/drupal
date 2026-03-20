<?php

declare(strict_types=1);

namespace Drupal\editor\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Provides an AJAX command for saving the contents of an editor dialog.
 *
 * This command is implemented in editor.dialog.js in
 * Drupal.AjaxCommands.prototype.editorDialogSave.
 */
class EditorDialogSave implements CommandInterface
{
    /**
     * Constructs an EditorDialogSave object.
     *
     * @param array $values
     *   The values that should be passed to the form constructor in Drupal.
     */
    public function __construct(
        /**
         * An array of values that will be passed back to the editor by the dialog.
         */
        protected array $values
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function render(): array
    {
        return [
          'command' => 'editorDialogSave',
          'values' => $this->values,
        ];
    }

}
