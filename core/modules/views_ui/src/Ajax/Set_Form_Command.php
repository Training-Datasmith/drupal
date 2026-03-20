<?php

declare(strict_types=1);

namespace Drupal\views_ui\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Provides an AJAX command for setting a form submit URL in modal forms.
 *
 * This command is implemented in Drupal.AjaxCommands.prototype.viewsSetForm.
 */
class SetFormCommand implements CommandInterface
{
    /**
     * Constructs a SetFormCommand object.
     *
     * @param string $url
     *   The URL of the form.
     */
    public function __construct(
        /**
         * The URL of the form.
         */
        protected $url
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function render(): array
    {
        return [
          'command' => 'viewsSetForm',
          'url' => $this->url,
        ];
    }

}
