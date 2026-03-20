<?php

declare (strict_types=1);
namespace Drupal\Core\Action\Plugin\Action;

use Drupal\Component\Render\Plain_Text_Output;
use Drupal\Core\Access\Access_Result;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\Configurable_Action_Base;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Drupal\Core\Form\Form_State_Interface;
use Drupal\Core\Plugin\Container_Factory_Plugin_Interface;
use Drupal\Core\Session\Account_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
use Drupal\Core\Utility\Token;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * Sends an email message.
 */
#[Action(id: 'action_send_email_action', label: new Translatable_Markup('Send email'), type: 'system')]
class Email_Action extends Configurable_Action_Base implements Container_Factory_Plugin_Interface
{
    /**
     * The user storage.
     *
     * @var \Drupal\Core\Entity\EntityStorageInterface
     */
    protected $storage;
    /**
     * Constructs an EmailAction object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Utility\Token $token
     *   The token service.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager.
     * @param \Psr\Log\LoggerInterface $logger
     *   A logger instance.
     * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
     *   The mail manager.
     * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
     *   The language manager.
     * @param \Drupal\Component\Utility\EmailValidatorInterface $emailValidator
     *   The email validator.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, protected \Drupal\Core\Utility\Token $token, Entity_Type_Manager_Interface $entity_type_manager, protected \Psr\Log\Logger_Interface $logger, protected \Drupal\Core\Mail\Mail_Manager_Interface $mail_manager, protected \Drupal\Core\Language\Language_Manager_Interface $language_manager, protected \Drupal\Component\Utility\Email_Validator_Interface $email_validator)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->storage = $entity_type_manager->get_storage('user');
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container, array $configuration, $plugin_id, $plugin_definition): static
    {
        return new static($configuration, $plugin_id, $plugin_definition, $container->get('token'), $container->get('entity_type.manager'), $container->get('logger.factory')->get('action'), $container->get('plugin.manager.mail'), $container->get('language_manager'), $container->get('email.validator'));
    }
    /**
     * {@inheritdoc}
     */
    public function execute($entity = null): void
    {
        if (empty($this->configuration['node'])) {
            $this->configuration['node'] = $entity;
        }
        $recipient = Plain_Text_Output::render_from_html($this->token->replace($this->configuration['recipient'], $this->configuration));
        // If the recipient is a registered user with a language preference, use
        // the recipient's preferred language. Otherwise, use the system default
        // language.
        $recipient_accounts = $this->storage->load_by_properties(['mail' => $recipient]);
        $recipient_account = reset($recipient_accounts);
        if ($recipient_account) {
            $langcode = $recipient_account->get_preferred_langcode();
        } else {
            $langcode = $this->language_manager->get_default_language()->get_id();
        }
        $params = ['context' => $this->configuration];
        $message = $this->mail_manager->mail('system', 'action_send_email', $recipient, $langcode, $params);
        // Error logging is handled by \Drupal\Core\Mail\MailManager::mail().
        if ($message['result']) {
            $this->logger->info('Sent email to %recipient', ['%recipient' => $recipient]);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function default_configuration(): array
    {
        return ['recipient' => '', 'subject' => '', 'message' => ''];
    }
    /**
     * {@inheritdoc}
     */
    public function build_configuration_form(array $form, Form_State_Interface $form_state): array
    {
        $form['recipient'] = ['#type' => 'textfield', '#title' => $this->t('Recipient email address'), '#default_value' => $this->configuration['recipient'], '#maxlength' => '254', '#description' => $this->t('You may also use tokens: [node:author:mail], [comment:author:mail], etc. Separate recipients with a comma.')];
        $form['subject'] = ['#type' => 'textfield', '#title' => $this->t('Subject'), '#default_value' => $this->configuration['subject'], '#maxlength' => '254', '#description' => $this->t('The subject of the message.')];
        $form['message'] = ['#type' => 'textarea', '#title' => $this->t('Message'), '#default_value' => $this->configuration['message'], '#cols' => '80', '#rows' => '20', '#description' => $this->t('The message that should be sent. You may include placeholders like [node:title], [user:account-name], [user:display-name] and [comment:body] to represent data that will be different each time message is sent. Not all placeholders will be available in all contexts.')];
        return $form;
    }
    /**
     * {@inheritdoc}
     */
    public function validate_configuration_form(array &$form, Form_State_Interface $form_state): void
    {
        if (!$this->email_validator->is_valid($form_state->get_value('recipient')) && !str_contains((string) $form_state->get_value('recipient'), ':mail')) {
            // We want the literal %author placeholder to be emphasized in the error
            // message.
            $form_state->set_error_by_name('recipient', $this->t('Enter a valid email address or use a token email address such as %author.', ['%author' => '[node:author:mail]']));
        }
    }
    /**
     * {@inheritdoc}
     */
    public function submit_configuration_form(array &$form, Form_State_Interface $form_state): void
    {
        $this->configuration['recipient'] = $form_state->get_value('recipient');
        $this->configuration['subject'] = $form_state->get_value('subject');
        $this->configuration['message'] = $form_state->get_value('message');
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