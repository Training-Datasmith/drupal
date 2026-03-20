<?php

declare (strict_types=1);
namespace Drupal\Core\Condition;

use Drupal\Core\Executable\Executable_Manager_Interface;
use Drupal\Core\Executable\Executable_Plugin_Base;
use Drupal\Core\Form\Form_State_Interface;
use Drupal\Core\Form\Subform_State_Interface;
use Drupal\Core\Plugin\Context_Aware_Plugin_Assignment_Trait;
/**
 * Provides a basis for fulfilling contexts for condition plugins.
 *
 * @see \Drupal\Core\Condition\Annotation\Condition
 * @see \Drupal\Core\Condition\Attribute\Condition
 * @see \Drupal\Core\Condition\ConditionInterface
 * @see \Drupal\Core\Condition\ConditionManager
 *
 * @ingroup plugin_api
 */
abstract class Condition_Plugin_Base extends Executable_Plugin_Base implements Condition_Interface
{
    use Context_Aware_Plugin_Assignment_Trait;
    /**
     * The condition manager to proxy execute calls through.
     *
     * @var \Drupal\Core\Executable\ExecutableManagerInterface
     */
    protected $executable_manager;
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
    public function is_negated()
    {
        return !empty($this->configuration['negate']);
    }
    /**
     * {@inheritdoc}
     */
    public function build_configuration_form(array $form, Form_State_Interface $form_state)
    {
        if ($form_state instanceof Subform_State_Interface) {
            $form_state = $form_state->get_complete_form_state();
        }
        $contexts = $form_state->get_temporary_value('gathered_contexts') ?: [];
        $form['context_mapping'] = $this->add_context_assignment_element($this, $contexts);
        $form['negate'] = ['#type' => 'checkbox', '#title' => $this->t('Negate the condition'), '#default_value' => $this->configuration['negate']];
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
        $this->configuration['negate'] = $form_state->get_value('negate');
        if ($form_state->has_value('context_mapping')) {
            $this->set_context_mapping($form_state->get_value('context_mapping'));
        }
    }
    /**
     * {@inheritdoc}
     */
    public function execute(?object $object = null)
    {
        return $this->executable_manager->execute($this);
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
    public function set_configuration(array $configuration)
    {
        $this->configuration = $configuration + $this->default_configuration();
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function default_configuration()
    {
        return ['negate' => false];
    }
    /**
     * {@inheritdoc}
     */
    public function calculate_dependencies()
    {
        return [];
    }
    /**
     * {@inheritdoc}
     */
    public function set_executable_manager(Executable_Manager_Interface $executable_manager)
    {
        $this->executable_manager = $executable_manager;
        return $this;
    }
}