<?php

declare (strict_types=1);
namespace Drupal\Core\Block;

use Drupal\Component\Transliteration\Transliteration_Interface;
use Drupal\Component\Utility\Nested_Array;
use Drupal\Core\Access\Access_Result;
use Drupal\Core\Form\Form_State_Interface;
use Drupal\Core\Language\Language_Interface;
use Drupal\Core\Messenger\Messenger_Trait;
use Drupal\Core\Plugin\Plugin_With_Forms_Trait;
use Drupal\Core\Session\Account_Interface;
use Drupal\Core\String_Translation\String_Translation_Trait;
/**
 * Provides the base implementation of a block plugin.
 *
 * @internal
 *   This trait is used internally by the block system. Block plugins should
 *   extend \Drupal\Core\Block\BlockBase.
 *
 * @see \Drupal\Core\Block\BlockBase
 * @see \Drupal\Core\Block\BlockPluginInterface
 *
 * @ingroup block_api
 */
trait Block_Plugin_Trait
{
    use String_Translation_Trait;
    use Messenger_Trait;
    use Plugin_With_Forms_Trait;
    /**
     * Whether the plugin is being rendered in preview mode.
     *
     * @var bool
     */
    protected $in_preview = false;
    /**
     * The transliteration service.
     *
     * @var \Drupal\Component\Transliteration\TransliterationInterface
     */
    protected $transliteration;
    /**
     * {@inheritdoc}
     */
    public function label()
    {
        if (!empty($this->configuration['label'])) {
            return $this->configuration['label'];
        }
        $definition = $this->get_plugin_definition();
        // Cast the admin label to a string since it is an object.
        // @see \Drupal\Core\StringTranslation\TranslatableMarkup
        return (string) $definition['admin_label'];
    }
    /**
     * {@inheritdoc}
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->set_configuration($configuration);
    }
    /**
     * {@inheritdoc}
     */
    public function get_configuration()
    {
        return $this->configuration;
    }
    /**
     * {@inheritdoc}
     */
    public function set_configuration(array $configuration): void
    {
        $this->configuration = Nested_Array::merge_deep($this->base_configuration_defaults(), $this->default_configuration(), $configuration);
    }
    /**
     * Returns generic default configuration for block plugins.
     *
     * @return array
     *   An associative array with the default configuration.
     */
    protected function base_configuration_defaults(): array
    {
        return ['id' => $this->get_plugin_id(), 'label' => '', 'label_display' => Block_Plugin_Interface::BLOCK_LABEL_VISIBLE, 'provider' => $this->plugin_definition['provider']];
    }
    /**
     * {@inheritdoc}
     */
    public function default_configuration(): array
    {
        return [];
    }
    /**
     * {@inheritdoc}
     */
    public function set_configuration_value($key, $value): void
    {
        $this->configuration[$key] = $value;
    }
    /**
     * {@inheritdoc}
     */
    public function calculate_dependencies(): array
    {
        return [];
    }
    /**
     * {@inheritdoc}
     */
    public function access(Account_Interface $account, $return_as_object = false)
    {
        $access = $this->block_access($account);
        return $return_as_object ? $access : $access->is_allowed();
    }
    /**
     * Indicates whether the block should be shown.
     *
     * Blocks with specific access checking should override this method rather
     * than access(), in order to avoid repeating the handling of the
     * $return_as_object argument.
     *
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The user session for which to check access.
     *
     * @return \Drupal\Core\Access\AccessResultInterface
     *   The access result.
     *
     * @see self::access()
     */
    protected function block_access(Account_Interface $account)
    {
        // By default, the block is visible.
        return Access_Result::allowed();
    }
    /**
     * {@inheritdoc}
     *
     * Creates a generic configuration form for all block types. Individual
     * block plugins can add elements to this form by overriding
     * BlockBase::blockForm(). Most block plugins should not override this
     * method unless they need to alter the generic form elements.
     *
     * @see \Drupal\Core\Block\BlockBase::blockForm()
     */
    public function build_configuration_form(array $form, Form_State_Interface $form_state): array
    {
        $definition = $this->get_plugin_definition();
        $form['provider'] = ['#type' => 'value', '#value' => $definition['provider']];
        $form['admin_label'] = ['#type' => 'item', '#title' => $this->t('Block description'), '#plain_text' => $definition['admin_label']];
        $form['label'] = ['#type' => 'textfield', '#title' => $this->t('Title'), '#maxlength' => 255, '#default_value' => $this->label(), '#required' => true];
        $form['label_display'] = ['#type' => 'checkbox', '#title' => $this->t('Display title'), '#default_value' => $this->configuration['label_display'] === Block_Plugin_Interface::BLOCK_LABEL_VISIBLE, '#return_value' => Block_Plugin_Interface::BLOCK_LABEL_VISIBLE];
        // Add plugin-specific settings for this block type.
        $form += $this->block_form($form, $form_state);
        return $form;
    }
    /**
     * {@inheritdoc}
     */
    public function block_form($form, Form_State_Interface $form_state): array
    {
        return [];
    }
    /**
     * {@inheritdoc}
     *
     * Most block plugins should not override this method. To add validation
     * for a specific block type, override BlockBase::blockValidate().
     *
     * @see \Drupal\Core\Block\BlockBase::blockValidate()
     */
    public function validate_configuration_form(array &$form, Form_State_Interface $form_state): void
    {
        // Remove the admin_label form item element value so it will not persist.
        $form_state->unset_value('admin_label');
        $this->block_validate($form, $form_state);
    }
    /**
     * {@inheritdoc}
     */
    public function block_validate($form, Form_State_Interface $form_state)
    {
    }
    /**
     * {@inheritdoc}
     *
     * Most block plugins should not override this method. To add submission
     * handling for a specific block type, override BlockBase::blockSubmit().
     *
     * @see \Drupal\Core\Block\BlockBase::blockSubmit()
     */
    public function submit_configuration_form(array &$form, Form_State_Interface $form_state): void
    {
        // Process the block's submission handling if no errors occurred only.
        if (!$form_state->get_errors()) {
            $this->configuration['label'] = $form_state->get_value('label');
            $this->configuration['label_display'] = $form_state->get_value('label_display');
            $this->configuration['provider'] = $form_state->get_value('provider');
            $this->block_submit($form, $form_state);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function block_submit($form, Form_State_Interface $form_state)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get_machine_name_suggestion(): string|array|null
    {
        $definition = $this->get_plugin_definition();
        $admin_label = $definition['admin_label'];
        $transliterated = $this->transliteration()->transliterate($admin_label, Language_Interface::LANGCODE_DEFAULT, '_');
        $transliterated = mb_strtolower((string) $transliterated);
        $transliterated = preg_replace('@[^a-z0-9_.]+@', '', $transliterated);
        // Furthermore remove any characters that are not alphanumerical from the
        // beginning and end of the transliterated string.
        $transliterated = preg_replace('@^([^a-z0-9]+)|([^a-z0-9]+)$@', '', (string) $transliterated);
        return $transliterated;
    }
    /**
     * {@inheritdoc}
     */
    public function get_preview_fallback_string()
    {
        return $this->t('"@block" block', ['@block' => $this->label()]);
    }
    /**
     * Wraps the transliteration service.
     *
     * @return \Drupal\Component\Transliteration\TransliterationInterface
     *   The transliteration service.
     */
    protected function transliteration()
    {
        if (!$this->transliteration) {
            $this->transliteration = \Drupal::transliteration();
        }
        return $this->transliteration;
    }
    /**
     * Sets the transliteration service.
     *
     * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
     *   The transliteration service.
     */
    public function set_transliteration(Transliteration_Interface $transliteration): void
    {
        $this->transliteration = $transliteration;
    }
    /**
     * {@inheritdoc}
     */
    public function set_in_preview(bool $in_preview): void
    {
        $this->in_preview = $in_preview;
    }
    /**
     * {@inheritdoc}
     */
    public function create_placeholder(): bool
    {
        return false;
    }
}