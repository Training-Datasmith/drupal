<?php

declare(strict_types=1);

namespace Drupal\Core\Ajax;

/**
 * Generic AJAX command for inserting content.
 *
 * This command instructs the client to insert the given render array or HTML
 * using whichever jQuery DOM manipulation method has been specified in the
 * #ajax['method'] variable of the element that triggered the request.
 *
 * This command is implemented by Drupal.AjaxCommands.prototype.insert()
 * defined in misc/ajax.js.
 *
 * @ingroup ajax
 */
class InsertCommand implements CommandInterface, CommandWithAttachedAssetsInterface
{
    use CommandWithAttachedAssetsTrait;

    /**
     * Constructs an InsertCommand object.
     *
     * @param string|null $selector
     *   A CSS selector.
     * @param string|array $content
     *   The content that will be inserted in the matched element(s), either a
     *   render array or an HTML string.
     * @param array $settings
     *   An array of JavaScript settings to be passed to any attached behaviors.
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
         * The content for the matched element(s).
         *
         * Either a render array or an HTML string.
         */
        protected $content,
        protected ?array $settings = null
    ) {
    }

    /**
     * Implements Drupal\Core\Ajax\CommandInterface:render().
     */
    public function render(): array
    {

        return [
          'command' => 'insert',
          'method' => null,
          'selector' => $this->selector,
          'data' => $this->getRenderedContent(),
          'settings' => $this->settings,
        ];
    }

}
