<?php

declare (strict_types=1);
namespace Drupal\Core\Action;

use Drupal\Component\Plugin\Configurable_Interface;
use Drupal\Component\Plugin\Dependent_Plugin_Interface;
use Drupal\Core\Form\Form_State_Interface;
use Drupal\Core\Plugin\Configurable_Trait;
use Drupal\Core\Plugin\Plugin_Form_Interface;
/**
 * Provides a base implementation for a configurable Action plugin.
 */
abstract class Configurable_Action_Base extends Action_Base implements Configurable_Interface, Dependent_Plugin_Interface, Plugin_Form_Interface
{
    use Configurable_Trait;
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
    public function validate_configuration_form(array &$form, Form_State_Interface $form_state)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function calculate_dependencies()
    {
        return [];
    }
}