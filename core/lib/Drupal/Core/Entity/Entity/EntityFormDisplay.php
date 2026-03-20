<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Entity;

use Drupal\Core\Entity\Attribute\Config_Entity_Type;
use Drupal\Core\Entity\Display\Entity_Form_Display_Interface;
use Drupal\Core\Entity\Entity\Access\Entity_Form_Display_Access_Control_Handler;
use Drupal\Core\Entity\Entity_Constraint_Violation_List_Interface;
use Drupal\Core\Entity\Entity_Display_Base;
use Drupal\Core\Entity\Entity_Display_Plugin_Collection;
use Drupal\Core\Entity\Fieldable_Entity_Interface;
use Drupal\Core\Form\Form_State_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
use Symfony\Component\Validator\Constraint_Violation;
use Symfony\Component\Validator\Constraint_Violation_List;
use Symfony\Component\Validator\Constraint_Violation_List_Interface;
/**
 * Configuration entity.
 *
 * Contains widget options for all components of an entity form in a given
 * form mode.
 */
#[Config_Entity_Type(id: 'entity_form_display', label: new Translatable_Markup('Entity form display'), entity_keys: ['id' => 'id', 'status' => 'status'], handlers: ['access' => Entity_Form_Display_Access_Control_Handler::class], constraints: ['ImmutableProperties' => ['properties' => ['id', 'targetEntityType', 'bundle', 'mode']]], config_export: ['id', 'targetEntityType', 'bundle', 'mode', 'content', 'hidden'])]
class Entity_Form_Display extends Entity_Display_Base implements Entity_Form_Display_Interface
{
    /**
     * {@inheritdoc}
     */
    protected $display_context = 'form';
    /**
     * Returns the entity_form_display object used to build an entity form.
     *
     * Depending on the configuration of the form mode for the entity bundle, this
     * can be either the display object associated with the form mode, or the
     * 'default' display.
     *
     * This method should only be used internally when rendering an entity form.
     * When assigning suggested display options for a component in a given form
     * mode, EntityDisplayRepositoryInterface::getFormDisplay() should be used
     * instead, in order to avoid inadvertently modifying the output of other form
     * modes that might happen to use the 'default' display too. Those options
     * will then be effectively applied only if the form mode is configured to use
     * them.
     *
     * hook_entity_form_display_alter() is invoked on each display, allowing 3rd
     * party code to alter the display options held in the display before they are
     * used to generate render arrays.
     *
     * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
     *   The entity for which the form is being built.
     * @param string $form_mode
     *   The form mode.
     * @param bool $default_fallback
     *   (optional) Whether the default display should be used to initialize the
     *   form display in case the specified display does not exist. Defaults to
     *   TRUE.
     *
     * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
     *   The display object that should be used to build the entity form.
     *
     * @see \Drupal\Core\Entity\EntityDisplayRepositoryInterface::getFormDisplay()
     * @see hook_entity_form_display_alter()
     */
    public static function collect_render_display(Fieldable_Entity_Interface $entity, string $form_mode, $default_fallback = true)
    {
        $entity_type = $entity->get_entity_type_id();
        $bundle = $entity->bundle();
        // Allow modules to change the form mode.
        \Drupal::module_handler()->alter([$entity_type . '_form_mode', 'entity_form_mode'], $form_mode, $entity);
        // Check the existence and status of:
        // - the display for the form mode,
        // - the 'default' display.
        if ($form_mode != 'default') {
            $candidate_ids[] = $entity_type . '.' . $bundle . '.' . $form_mode;
        }
        if ($default_fallback) {
            $candidate_ids[] = $entity_type . '.' . $bundle . '.default';
        }
        $results = \Drupal::entity_query('entity_form_display')->condition('id', $candidate_ids)->condition('status', true)->execute();
        // Load the first valid candidate display, if any.
        $storage = \Drupal::entity_type_manager()->get_storage('entity_form_display');
        foreach ($candidate_ids as $candidate_id) {
            if (isset($results[$candidate_id])) {
                $display = $storage->load($candidate_id);
                break;
            }
        }
        // Else create a fresh runtime object.
        if (empty($display)) {
            $display = $storage->create(['targetEntityType' => $entity_type, 'bundle' => $bundle, 'mode' => $default_fallback ? $form_mode : static::CUSTOM_MODE, 'status' => true]);
        }
        // Let the display know which form mode was originally requested.
        $display->original_mode = $form_mode;
        // Let modules alter the display.
        $display_context = ['entity_type' => $entity_type, 'bundle' => $bundle, 'form_mode' => $form_mode];
        \Drupal::module_handler()->alter('entity_form_display', $display, $display_context);
        return $display;
    }
    /**
     * {@inheritdoc}
     */
    public function __construct(array $values, $entity_type)
    {
        $this->plugin_manager = \Drupal::service('plugin.manager.field.widget');
        parent::__construct($values, $entity_type);
    }
    /**
     * {@inheritdoc}
     */
    public function get_renderer($field_name)
    {
        if (isset($this->plugins[$field_name])) {
            return $this->plugins[$field_name];
        }
        // Instantiate the widget object from the stored display properties.
        if (($configuration = $this->get_component($field_name)) && isset($configuration['type']) && $definition = $this->get_field_definition($field_name)) {
            $widget = $this->plugin_manager->get_instance([
                'field_definition' => $definition,
                'form_mode' => $this->original_mode,
                // No need to prepare, defaults have been merged in setComponent().
                'prepare' => false,
                'configuration' => $configuration,
            ]);
        } else {
            $widget = null;
        }
        // Persist the widget object.
        $this->plugins[$field_name] = $widget;
        return $widget;
    }
    /**
     * {@inheritdoc}
     */
    public function build_form(Fieldable_Entity_Interface $entity, array &$form, Form_State_Interface $form_state): void
    {
        // Set #parents to 'top-level' by default.
        $form += ['#parents' => []];
        // Let each widget generate the form elements.
        foreach ($this->get_components() as $name => $options) {
            if ($widget = $this->get_renderer($name)) {
                $items = $entity->get($name);
                $items->filter_empty_items();
                $form[$name] = $widget->form($items, $form, $form_state);
                $form[$name]['#access'] = $items->access('edit');
                // Assign the correct weight. This duplicates the reordering done in
                // processForm(), but is needed for other forms calling this method
                // directly.
                $form[$name]['#weight'] = $options['weight'];
                // Associate the cache tags for the field definition & field storage
                // definition.
                $field_definition = $this->get_field_definition($name);
                $this->renderer->add_cacheable_dependency($form[$name], $field_definition);
                $this->renderer->add_cacheable_dependency($form[$name], $field_definition->get_field_storage_definition());
            }
        }
        // Associate the cache tags for the form display.
        $this->renderer->add_cacheable_dependency($form, $this);
        // The form might not have the correct cacheability metadata, so make it
        // uncacheable by default.
        // @todo Remove this in https://www.drupal.org/node/3395524.
        $form['#cache']['max-age'] = 0;
        // Add a process callback so we can assign weights and hide extra fields.
        $form['#process'][] = $this->process_form(...);
    }
    /**
     * Process callback: assigns weights and hides extra fields.
     *
     * @see \Drupal\Core\Entity\Entity\EntityFormDisplay::buildForm()
     */
    public function process_form(array $element, Form_State_Interface $form_state, $form): array
    {
        // Assign the weights configured in the form display.
        foreach ($this->get_components() as $name => $options) {
            if (isset($element[$name])) {
                $element[$name]['#weight'] = $options['weight'];
            }
        }
        // Hide extra fields.
        $extra_fields = \Drupal::service('entity_field.manager')->get_extra_fields($this->target_entity_type, $this->bundle);
        $extra_fields = $extra_fields['form'] ?? [];
        foreach ($extra_fields as $extra_field => $info) {
            if (!$this->get_component($extra_field)) {
                $element[$extra_field]['#access'] = false;
            }
        }
        return $element;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function extract_form_values(Fieldable_Entity_Interface $entity, array &$form, Form_State_Interface $form_state): array
    {
        $extracted = [];
        foreach ($entity as $name => $items) {
            if ($widget = $this->get_renderer($name)) {
                $widget->extract_form_values($items, $form, $form_state);
                $extracted[$name] = $name;
            }
        }
        return $extracted;
    }
    /**
     * {@inheritdoc}
     */
    public function validate_form_values(Fieldable_Entity_Interface $entity, array &$form, Form_State_Interface $form_state): void
    {
        $violations = $entity->validate();
        $violations->filter_by_field_access();
        // Flag entity level violations.
        foreach ($violations->get_entity_violations() as $violation) {
            /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
            $form_state->set_error($form, $violation->get_message());
        }
        $this->flag_widgets_errors_from_violations($violations, $form, $form_state);
    }
    /**
     * {@inheritdoc}
     */
    public function flag_widgets_errors_from_violations(Entity_Constraint_Violation_List_Interface $violations, array &$form, Form_State_Interface $form_state): void
    {
        $entity = $violations->get_entity();
        foreach ($violations->get_field_names() as $field_name) {
            // Only show violations for fields that actually appear in the form, and
            // let the widget assign the violations to the correct form elements.
            if ($widget = $this->get_renderer($field_name)) {
                $field_violations = $this->move_property_path_violations_relative_to_field($field_name, $violations->get_by_field($field_name));
                $widget->flag_errors($entity->get($field_name), $field_violations, $form, $form_state);
            }
        }
    }
    /**
     * Moves the property path to be relative to field level.
     *
     * @param string $field_name
     *   The field name.
     * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
     *   The violations.
     *
     * @return \Symfony\Component\Validator\ConstraintViolationList
     *   A new constraint violation list with the changed property path.
     */
    protected function move_property_path_violations_relative_to_field($field_name, Constraint_Violation_List_Interface $violations): \Symfony\Component\Validator\Constraint_Violation_List
    {
        $new_violations = new Constraint_Violation_List();
        foreach ($violations as $violation) {
            // All the logic below is necessary to change the property path of the
            // violations to be relative to the item list, so like title.0.value gets
            // changed to 0.value. Sadly constraints in Symfony don't have setters so
            // we have to create new objects.
            /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
            // Create a new violation object with just a different property path.
            $violation_path = $violation->get_property_path();
            $path_parts = explode('.', (string) $violation_path);
            if ($path_parts[0] === $field_name) {
                unset($path_parts[0]);
            }
            $new_path = implode('.', $path_parts);
            $constraint = null;
            $cause = null;
            $parameters = [];
            $plural = null;
            if ($violation instanceof Constraint_Violation) {
                $constraint = $violation->get_constraint();
                $cause = $violation->get_cause();
                $parameters = $violation->get_parameters();
                $plural = $violation->get_plural();
            }
            $new_violation = new Constraint_Violation($violation->get_message(), $violation->get_message_template(), $parameters, $violation->get_root(), $new_path, $violation->get_invalid_value(), $plural, $violation->get_code(), $constraint, $cause);
            $new_violations->add($new_violation);
        }
        return $new_violations;
    }
    /**
     * {@inheritdoc}
     */
    public function get_plugin_collections(): array
    {
        $configurations = [];
        foreach ($this->get_components() as $field_name => $configuration) {
            if (!empty($configuration['type']) && $field_definition = $this->get_field_definition($field_name)) {
                $configurations[$configuration['type']] = $configuration + ['field_definition' => $field_definition, 'form_mode' => $this->mode];
            }
        }
        return ['widgets' => new Entity_Display_Plugin_Collection($this->plugin_manager, $configurations)];
    }
}