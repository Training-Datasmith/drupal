<?php

declare (strict_types=1);
namespace Drupal\Core\Block;

use Drupal\Component\Plugin\Configurable_Interface;
use Drupal\Component\Plugin\Dependent_Plugin_Interface;
use Drupal\Component\Plugin\Derivative_Inspection_Interface;
use Drupal\Component\Plugin\Plugin_Inspection_Interface;
use Drupal\Core\Cache\Cacheable_Dependency_Interface;
use Drupal\Core\Form\Form_State_Interface;
use Drupal\Core\Plugin\Plugin_Form_Interface;
use Drupal\Core\Session\Account_Interface;
/**
 * Defines the required interface for all block plugins.
 *
 * @todo Add detailed documentation here explaining the block system's
 *   architecture and the relationships between the various objects, including
 *   brief references to the important components that are not coupled to the
 *   interface.
 *
 * @ingroup block_api
 */
interface Block_Plugin_Interface extends Configurable_Interface, Dependent_Plugin_Interface, Plugin_Form_Interface, Plugin_Inspection_Interface, Cacheable_Dependency_Interface, Derivative_Inspection_Interface
{
    /**
     * Indicates the block label (title) should be displayed to end users.
     */
    public const BLOCK_LABEL_VISIBLE = 'visible';
    /**
     * Returns the user-facing block label.
     *
     * @todo Provide other specific label-related methods in
     *   https://www.drupal.org/node/2025649.
     *
     * @return string
     *   The block label.
     */
    public function label();
    /**
     * Indicates whether the block should be shown.
     *
     * This method allows base implementations to add general access restrictions
     * that should apply to all extending block plugins.
     *
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The user session for which to check access.
     * @param bool $return_as_object
     *   (optional) Defaults to FALSE.
     *
     * @return ($return_as_object is true ? \Drupal\Core\Access\AccessResultInterface : bool)
     *   The access result. Returns a boolean if $return_as_object is FALSE (this
     *   is the default) and otherwise an AccessResultInterface object.
     *   When a boolean is returned, the result of AccessInterface::isAllowed() is
     *   returned, i.e. TRUE means access is explicitly allowed, FALSE means
     *   access is either explicitly forbidden or "no opinion".
     *
     * @see \Drupal\block\BlockAccessControlHandler
     */
    public function access(Account_Interface $account, $return_as_object = false);
    /**
     * Builds and returns the renderable array for this block plugin.
     *
     * If a block should not be rendered because it has no content, then this
     * method must also ensure to return no content: it must then only return an
     * empty array, or an empty array with #cache set (with cacheability metadata
     * indicating the circumstances for it being empty).
     *
     * @return array
     *   A renderable array representing the content of the block.
     *
     * @see \Drupal\block\BlockViewBuilder
     */
    public function build();
    /**
     * Whether to render blocks in a placeholder.
     *
     * When blocks of this type are rendered, indicate whether they should be
     * rendered in a placeholder or not. In general, blocks that attach libraries
     * and/or render entities should be placeholdered to optimize various aspects
     * of rendering performance.
     *
     * @return bool
     *   Whether to placeholder blocks of this plugin type.
     */
    public function create_placeholder(): bool;
    /**
     * Sets a particular value in the block settings.
     *
     * @param string $key
     *   The key of PluginBase::$configuration to set.
     * @param mixed $value
     *   The value to set for the provided key.
     *
     * @todo This doesn't belong here. Move this into a new base class in
     *   https://www.drupal.org/node/1764380.
     * @todo This does not set a value in \Drupal::config(), so the name is confusing.
     *
     * @see \Drupal\Component\Plugin\PluginBase::$configuration
     */
    public function set_configuration_value($key, $value);
    /**
     * Returns the configuration form elements specific to this block plugin.
     *
     * Blocks that need to add form elements to the normal block configuration
     * form should implement this method.
     *
     * @param array $form
     *   The form definition array for the block configuration form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The renderable form array representing the entire configuration form.
     */
    public function block_form($form, Form_State_Interface $form_state);
    /**
     * Adds block type-specific validation for the block form.
     *
     * Note that this method takes the form structure and form state for the full
     * block configuration form as arguments, not just the elements defined in
     * BlockPluginInterface::blockForm().
     *
     * @param array $form
     *   The form definition array for the full block configuration form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @see \Drupal\Core\Block\BlockPluginInterface::blockForm()
     * @see \Drupal\Core\Block\BlockPluginInterface::blockSubmit()
     */
    public function block_validate($form, Form_State_Interface $form_state);
    /**
     * Adds block type-specific submission handling for the block form.
     *
     * Note that this method takes the form structure and form state for the full
     * block configuration form as arguments, not just the elements defined in
     * BlockPluginInterface::blockForm().
     *
     * @param array $form
     *   The form definition array for the full block configuration form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @see \Drupal\Core\Block\BlockPluginInterface::blockForm()
     * @see \Drupal\Core\Block\BlockPluginInterface::blockValidate()
     */
    public function block_submit($form, Form_State_Interface $form_state);
    /**
     * Suggests a machine name to identify an instance of this block.
     *
     * The block plugin need not verify that the machine name is at all unique. It
     * is only responsible for providing a baseline suggestion; calling code is
     * responsible for ensuring whatever uniqueness is required for the use case.
     *
     * @return string
     *   The suggested machine name.
     */
    public function get_machine_name_suggestion();
}