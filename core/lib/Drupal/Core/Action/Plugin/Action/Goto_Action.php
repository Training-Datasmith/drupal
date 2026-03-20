<?php

declare (strict_types=1);
namespace Drupal\Core\Action\Plugin\Action;

use Drupal\Component\Utility\Url_Helper;
use Drupal\Core\Access\Access_Result;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\Configurable_Action_Base;
use Drupal\Core\Form\Form_State_Interface;
use Drupal\Core\Plugin\Container_Factory_Plugin_Interface;
use Drupal\Core\Session\Account_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
use Symfony\Component\Http_Foundation\Redirect_Response;
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * Redirects to a different URL.
 */
#[Action(id: 'action_goto_action', label: new Translatable_Markup('Redirect to URL'), type: 'system')]
class Goto_Action extends Configurable_Action_Base implements Container_Factory_Plugin_Interface
{
    /**
     * Constructs a GotoAction object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher
     *   The tempstore factory.
     * @param \Drupal\Core\Utility\UnroutedUrlAssemblerInterface $unroutedUrlAssembler
     *   The unrouted URL assembler service.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, protected \Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface $dispatcher, protected \Drupal\Core\Utility\Unrouted_Url_Assembler_Interface $unrouted_url_assembler)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }
    /**
     * {@inheritdoc}
     */
    public function execute($object = null): void
    {
        $url = $this->configuration['url'];
        // Leave external URLs unchanged, and assemble others as absolute URLs
        // relative to the site's base URL.
        if (!Url_Helper::is_external($url)) {
            $parts = Url_Helper::parse($url);
            // @todo '<front>' is valid input for BC reasons, may be removed by
            //   https://www.drupal.org/node/2421941
            if ($parts['path'] === '<front>') {
                $parts['path'] = '';
            }
            $uri = 'base:' . $parts['path'];
            $options = ['query' => $parts['query'], 'fragment' => $parts['fragment'], 'absolute' => true];
            // Treat this as if it's user input of a path relative to the site's
            // base URL.
            $url = $this->unrouted_url_assembler->assemble($uri, $options);
        }
        $response = new Redirect_Response($url);
        $listener = function ($event) use ($response): void {
            $event->set_response($response);
        };
        // Add the listener to the event dispatcher.
        $this->dispatcher->add_listener(Kernel_Events::RESPONSE, $listener);
    }
    /**
     * {@inheritdoc}
     */
    public function default_configuration(): array
    {
        return ['url' => ''];
    }
    /**
     * {@inheritdoc}
     */
    public function build_configuration_form(array $form, Form_State_Interface $form_state): array
    {
        $form['url'] = ['#type' => 'textfield', '#title' => $this->t('URL'), '#description' => $this->t('The URL to which the user should be redirected. This can be an internal URL like /node/1234 or an external URL like @url.', ['@url' => 'https://example.com']), '#default_value' => $this->configuration['url'], '#required' => true];
        return $form;
    }
    /**
     * {@inheritdoc}
     */
    public function submit_configuration_form(array &$form, Form_State_Interface $form_state): void
    {
        $this->configuration['url'] = $form_state->get_value('url');
    }
    /**
     * {@inheritdoc}
     */
    public function access($object, ?Account_Interface $account = null, $return_as_object = false)
    {
        $access = Access_Result::allowed();
        return $return_as_object ? $access : $access->is_allowed();
    }
}