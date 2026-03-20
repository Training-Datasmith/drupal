<?php

declare (strict_types=1);
namespace Drupal\Core\Display;

use Drupal\Core\Cache\Refinable_Cacheable_Dependency_Trait;
use Drupal\Core\Form\Form_State_Interface;
use Drupal\Core\Plugin\Configurable_Plugin_Base;
use Drupal\Core\Plugin\Plugin_Dependency_Trait;
use Drupal\Core\Session\Account_Interface;
/**
 * Provides a base class for DisplayVariant plugins.
 *
 * @see \Drupal\Core\Display\Attribute\DisplayVariant
 * @see \Drupal\Core\Display\VariantInterface
 * @see \Drupal\Core\Display\VariantManager
 * @see plugin_api
 */
abstract class Variant_Base extends Configurable_Plugin_Base implements Variant_Interface
{
    use Plugin_Dependency_Trait;
    use Refinable_Cacheable_Dependency_Trait;
    /**
     * {@inheritdoc}
     */
    public function label()
    {
        return $this->configuration['label'];
    }
    /**
     * {@inheritdoc}
     */
    public function admin_label()
    {
        return $this->plugin_definition['admin_label'];
    }
    /**
     * {@inheritdoc}
     */
    public function id()
    {
        return $this->configuration['uuid'];
    }
    /**
     * {@inheritdoc}
     */
    public function get_weight()
    {
        return (int) $this->configuration['weight'];
    }
    /**
     * {@inheritdoc}
     */
    public function set_weight($weight): void
    {
        $this->configuration['weight'] = (int) $weight;
    }
    /**
     * {@inheritdoc}
     */
    public function get_configuration()
    {
        return ['id' => $this->get_plugin_id()] + $this->configuration;
    }
    /**
     * {@inheritdoc}
     */
    public function default_configuration()
    {
        return ['label' => '', 'uuid' => '', 'weight' => 0];
    }
    /**
     * {@inheritdoc}
     */
    public function calculate_dependencies()
    {
        return $this->dependencies;
    }
    /**
     * {@inheritdoc}
     */
    public function build_configuration_form(array $form, Form_State_Interface $form_state)
    {
        $form['label'] = ['#type' => 'textfield', '#title' => $this->t('Label'), '#description' => $this->t('The label for this display variant.'), '#default_value' => $this->label(), '#maxlength' => '255'];
        return $form;
    }
    /**
     * {@inheritdoc}
     */
    public function validate_configuration_form(array &$form, Form_State_Interface $form_state)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function submit_configuration_form(array &$form, Form_State_Interface $form_state): void
    {
        $this->configuration['label'] = $form_state->get_value('label');
    }
    /**
     * {@inheritdoc}
     */
    public function access(?Account_Interface $account = null)
    {
        return true;
    }
}