<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

use Drupal\Component\Utility\Url_Helper;
/**
 * Provides an AJAX command for opening a modal with URL.
 *
 * OpenDialogCommand is a similar class which opens modals but works
 * differently as it needs all data to be passed through dialogOptions while
 * OpenModalDialogWithUrl fetches the data from routing info of the URL.
 *
 * @see \Drupal\Core\Ajax\OpenDialogCommand
 */
class Open_Modal_Dialog_With_Url implements Command_Interface
{
    /**
     * Constructs a OpenModalDialogWithUrl object.
     *
     * @param string $url
     *   Only Internal URLs or URLs with the same domain and base path are
     *   allowed.
     * @param array $settings
     *   The dialog settings.
     */
    public function __construct(protected string $url, protected array $settings)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function render(): array
    {
        // @see \Drupal\Core\Routing\LocalAwareRedirectResponseTrait::isLocal()
        if (!Url_Helper::is_external($this->url) || Url_Helper::external_is_local($this->url, $this->get_base_url())) {
            return ['command' => 'openModalDialogWithUrl', 'url' => $this->url, 'dialogOptions' => $this->settings];
        }
        throw new \LogicException('External URLs are not allowed.');
    }
    /**
     * Gets the complete base URL.
     */
    private function get_base_url()
    {
        $request_context = \Drupal::service('router.request_context');
        return $request_context->get_complete_base_url();
    }
}