<?php

declare (strict_types=1);
namespace Drupal\Core\Action\Plugin\Action;

use Drupal\Core\Access\Access_Result;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\Configurable_Action_Base;
use Drupal\Core\Form\Form_State_Interface;
use Drupal\Core\Messenger\Messenger_Interface;
use Drupal\Core\Plugin\Container_Factory_Plugin_Interface;
use Drupal\Core\Session\Account_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
use Drupal\Core\Utility\Token;
/**
 * Sends a message to the current user's screen.
 */
#[Action(id: 'action_message_action', label: new Translatable_Markup('Display a message to the user'), type: 'system')]
class Message_Action extends Configurable_Action_Base implements Container_Factory_Plugin_Interface
{
    /**
     * The messenger.
     *
     * @var \Drupal\Core\Messenger\MessengerInterface
     */
    protected $messenger;
    /**
     * Constructs a MessageAction object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Utility\Token $token
     *   The token service.
     * @param \Drupal\Core\Render\RendererInterface $renderer
     *   The renderer.
     * @param \Drupal\Core\Messenger\MessengerInterface $messenger
     *   The messenger.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, protected \Drupal\Core\Utility\Token $token, protected \Drupal\Core\Render\Renderer_Interface $renderer, Messenger_Interface $messenger)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->messenger = $messenger;
    }
    /**
     * {@inheritdoc}
     */
    public function execute($entity = null): void
    {
        if (empty($this->configuration['node'])) {
            $this->configuration['node'] = $entity;
        }
        $message = $this->token->replace($this->configuration['message'], $this->configuration);
        $build = ['#markup' => $message];
        // @todo Fix in https://www.drupal.org/node/2577827
        $this->messenger->add_status($this->renderer->render_in_isolation($build));
    }
    /**
     * {@inheritdoc}
     */
    public function default_configuration(): array
    {
        return ['message' => ''];
    }
    /**
     * {@inheritdoc}
     */
    public function build_configuration_form(array $form, Form_State_Interface $form_state): array
    {
        $form['message'] = ['#type' => 'textarea', '#title' => $this->t('Message'), '#default_value' => $this->configuration['message'], '#required' => true, '#rows' => '8', '#description' => $this->t('The message to be displayed to the current user. You may include placeholders like [node:title], [user:account-name], [user:display-name] and [comment:body] to represent data that will be different each time message is sent. Not all placeholders will be available in all contexts.')];
        return $form;
    }
    /**
     * {@inheritdoc}
     */
    public function submit_configuration_form(array &$form, Form_State_Interface $form_state): void
    {
        $this->configuration['message'] = $form_state->get_value('message');
        unset($this->configuration['node']);
    }
    /**
     * {@inheritdoc}
     */
    public function access($object, ?Account_Interface $account = null, $return_as_object = false)
    {
        $result = Access_Result::allowed();
        return $return_as_object ? $result : $result->is_allowed();
    }
}