<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

use Drupal\Core\Asset\Asset_Collection_Renderer_Interface;
use Drupal\Core\Asset\Asset_Resolver_Interface;
use Drupal\Core\Asset\Attached_Assets;
use Drupal\Core\Config\Config_Factory_Interface;
use Drupal\Core\Extension\Module_Handler_Interface;
use Drupal\Core\Language\Language_Manager_Interface;
use Drupal\Core\Render\Attachments_Interface;
use Drupal\Core\Render\Attachments_Response_Processor_Interface;
use Drupal\Core\Render\Renderer_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
/**
 * Processes attachments of AJAX responses.
 *
 * @see \Drupal\Core\Ajax\AjaxResponse
 * @see \Drupal\Core\Render\MainContent\AjaxRenderer
 */
class Ajax_Response_Attachments_Processor implements Attachments_Response_Processor_Interface
{
    /**
     * A config object for the system performance configuration.
     *
     * @var \Drupal\Core\Config\Config
     */
    protected $config;
    /**
     * Constructs an AjaxResponseAttachmentsProcessor object.
     *
     * @param \Drupal\Core\Asset\AssetResolverInterface $assetResolver
     *   An asset resolver.
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   A config factory for retrieving required config objects.
     * @param \Drupal\Core\Asset\AssetCollectionRendererInterface $cssCollectionRenderer
     *   The CSS asset collection renderer.
     * @param \Drupal\Core\Asset\AssetCollectionRendererInterface $jsCollectionRenderer
     *   The JS asset collection renderer.
     * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
     *   The request stack.
     * @param \Drupal\Core\Render\RendererInterface $renderer
     *   The renderer.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
     *   The module handler.
     * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
     *   The language manager.
     */
    public function __construct(protected Asset_Resolver_Interface $asset_resolver, protected Config_Factory_Interface $config_factory, protected Asset_Collection_Renderer_Interface $css_collection_renderer, protected Asset_Collection_Renderer_Interface $js_collection_renderer, protected Request_Stack $request_stack, protected Renderer_Interface $renderer, protected Module_Handler_Interface $module_handler, protected Language_Manager_Interface $language_manager)
    {
        $this->config = $config_factory->get('system.performance');
    }
    /**
     * {@inheritdoc}
     */
    public function process_attachments(Attachments_Interface $response): Attachments_Interface
    {
        assert($response instanceof Ajax_Response, '\Drupal\Core\Ajax\AjaxResponse instance expected.');
        $request = $this->request_stack->get_current_request();
        if ($response->get_content() == '{}') {
            $response->set_data($this->build_attachments_commands($response, $request));
        }
        return $response;
    }
    /**
     * Prepares the AJAX commands to attach assets.
     *
     * @param \Drupal\Core\Ajax\AjaxResponse $response
     *   The AJAX response to update.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request object that the AJAX is responding to.
     *
     * @return array
     *   An array of commands ready to be returned as JSON.
     */
    protected function build_attachments_commands(Ajax_Response $response, Request $request)
    {
        $ajax_page_state = $request->attributes->get('ajax_page_state');
        $maintenance_mode = defined('MAINTENANCE_MODE') || \Drupal::state()->get('system.maintenance_mode');
        // Aggregate CSS/JS if necessary, but only during normal site operation.
        $optimize_css = !$maintenance_mode && $this->config->get('css.preprocess');
        $optimize_js = !$maintenance_mode && $this->config->get('js.preprocess');
        $attachments = $response->get_attachments();
        // Resolve the attached libraries into asset collections.
        $assets = new Attached_Assets();
        $assets->set_libraries($attachments['library'] ?? [])->set_already_loaded_libraries(isset($ajax_page_state['libraries']) ? explode(',', $ajax_page_state['libraries']) : [])->set_settings($attachments['drupalSettings'] ?? []);
        $css_assets = $this->asset_resolver->get_css_assets($assets, $optimize_css, $this->language_manager->get_current_language());
        [$js_assets_header, $js_assets_footer] = $this->asset_resolver->get_js_assets($assets, $optimize_js, $this->language_manager->get_current_language());
        // First, AttachedAssets::setLibraries() ensures duplicate libraries are
        // removed: it converts it to a set of libraries if necessary. Second,
        // AssetResolver::getJsSettings() ensures $assets contains the final set of
        // JavaScript settings. AttachmentsResponseProcessorInterface also mandates
        // that the response it processes contains the final attachment values, so
        // update both the 'library' and 'drupalSettings' attachments accordingly.
        $attachments['library'] = $assets->get_libraries();
        $attachments['drupalSettings'] = $assets->get_settings();
        $response->set_attachments($attachments);
        // Render the HTML to load these files, and add AJAX commands to insert this
        // HTML in the page. Settings are handled separately, afterwards.
        $settings = [];
        if (isset($js_assets_header['drupalSettings'])) {
            $settings = $js_assets_header['drupalSettings']['data'];
            unset($js_assets_header['drupalSettings']);
        }
        if (isset($js_assets_footer['drupalSettings'])) {
            $settings = $js_assets_footer['drupalSettings']['data'];
            unset($js_assets_footer['drupalSettings']);
        }
        // Prepend commands to add the assets, preserving their relative order.
        $resource_commands = [];
        if ($css_assets) {
            $css_render_array = $this->css_collection_renderer->render($css_assets);
            $resource_commands[] = new Add_Css_Command(array_column($css_render_array, '#attributes'));
        }
        if ($js_assets_header) {
            $js_header_render_array = $this->js_collection_renderer->render($js_assets_header);
            $resource_commands[] = new Add_Js_Command(array_column($js_header_render_array, '#attributes'), 'head');
        }
        if ($js_assets_footer) {
            $js_footer_render_array = $this->js_collection_renderer->render($js_assets_footer);
            $resource_commands[] = new Add_Js_Command(array_column($js_footer_render_array, '#attributes'));
        }
        foreach (array_reverse($resource_commands) as $resource_command) {
            $response->add_command($resource_command, true);
        }
        // Prepend a command to merge changes and additions to drupalSettings.
        if (!empty($settings)) {
            // During Ajax requests basic path-specific settings are excluded from
            // new drupalSettings values. The original page where this request comes
            // from already has the right values. An Ajax request would update them
            // with values for the Ajax request and incorrectly override the page's
            // values.
            // @see system_js_settings_alter()
            unset($settings['path']);
            $response->add_command(new Settings_Command($settings, true), true);
        }
        $commands = $response->get_commands();
        $this->module_handler->alter('ajax_render', $commands);
        return $commands;
    }
}