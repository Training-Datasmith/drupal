<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

/**
 * Defines an AJAX command to set the window.location, loading that URL.
 *
 * @ingroup ajax
 */
class Redirect_Command implements Command_Interface
{
    /**
     * Constructs an RedirectCommand object.
     *
     * @param string $url
     *   The URL that will be loaded into window.location. This should be a full
     *   URL.
     */
    public function __construct(
        /**
         * The URL that will be loaded into window.location.
         */
        protected $url
    )
    {
    }
    /**
     * Implements \Drupal\Core\Ajax\CommandInterface:render().
     */
    public function render(): array
    {
        return ['command' => 'redirect', 'url' => $this->url];
    }
}