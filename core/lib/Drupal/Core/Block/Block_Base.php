<?php

declare (strict_types=1);
namespace Drupal\Core\Block;

use Drupal\Core\Form\Form_State_Interface;
use Drupal\Core\Plugin\Context_Aware_Plugin_Assignment_Trait;
use Drupal\Core\Plugin\Context_Aware_Plugin_Interface;
use Drupal\Core\Plugin\Context_Aware_Plugin_Trait;
use Drupal\Core\Plugin\Plugin_Base;
use Drupal\Core\Plugin\Plugin_With_Forms_Interface;
use Drupal\Core\Plugin\Preview_Aware_Plugin_Interface;
use Drupal\Core\Render\Preview_Fallback_Interface;
/**
 * Defines a base block implementation that most blocks plugins will extend.
 *
 * This abstract class provides the generic block configuration form, default
 * block settings, and handling for general user-defined block visibility
 * settings.
 *
 * @ingroup block_api
 */
abstract class Block_Base extends Plugin_Base implements Block_Plugin_Interface, Plugin_With_Forms_Interface, Preview_Aware_Plugin_Interface, Preview_Fallback_Interface, Context_Aware_Plugin_Interface
{
    use Block_Plugin_Trait {
        buildConfigurationForm as traitBuildConfigurationForm;
        submitConfigurationForm as traitSubmitConfigurationForm;
    }
    use Context_Aware_Plugin_Trait;
    use Context_Aware_Plugin_Assignment_Trait;
    /**
     * {@inheritdoc}
     */
    public function build_configuration_form(array $form, Form_State_Interface $form_state)
    {
        $form = $this->trait_build_configuration_form($form, $form_state);
        // Add context mapping UI form elements.
        $contexts = $form_state->get_temporary_value('gathered_contexts') ?: [];
        $form['context_mapping'] = $this->add_context_assignment_element($this, $contexts);
        return $form;
    }
    /**
     * {@inheritdoc}
     */
    public function submit_configuration_form(array &$form, Form_State_Interface $form_state): void
    {
        if (!$form_state->get_errors() && $form_state->get_value('context_mapping')) {
            $this->configuration['context_mapping'] = $form_state->get_value('context_mapping');
        }
        $this->trait_submit_configuration_form($form, $form_state);
    }
}