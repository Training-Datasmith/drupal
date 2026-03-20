<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

use Drupal\Core\Entity\Display\Entity_Form_Display_Interface;
use Drupal\Core\Entity\Entity\Entity_Form_Display;
use Drupal\Core\Form\Form_State_Interface;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * Entity form variant for content entity types.
 *
 * @see \Drupal\Core\ContentEntityBase
 */
class Content_Entity_Form extends Entity_Form implements Content_Entity_Form_Interface
{
    /**
     * The entity being used by this form.
     *
     * @var \Drupal\Core\Entity\ContentEntityInterface|\Drupal\Core\Entity\RevisionLogInterface
     */
    protected $entity;
    /**
     * Constructs a ContentEntityForm object.
     *
     * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
     *   The entity repository service.
     * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
     *   The entity type bundle service.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(protected \Drupal\Core\Entity\Entity_Repository_Interface $entity_repository, protected \Drupal\Core\Entity\Entity_Type_Bundle_Info_Interface $entity_type_bundle_info, protected \Drupal\Component\Datetime\Time_Interface $time)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container): static
    {
        return new static($container->get('entity.repository'), $container->get('entity_type.bundle.info'), $container->get('datetime.time'));
    }
    /**
     * {@inheritdoc}
     */
    protected function prepare_entity()
    {
        parent::prepare_entity();
        // Hide the current revision log message in UI.
        if ($this->show_revision_ui() && !$this->entity->is_new() && $this->entity instanceof Revision_Log_Interface) {
            $this->entity->set_revision_log_message(null);
        }
    }
    /**
     * Returns the bundle entity of the entity, or NULL if there is none.
     *
     * @return \Drupal\Core\Entity\EntityInterface|null
     *   The bundle entity.
     */
    protected function get_bundle_entity()
    {
        if ($bundle_entity_type = $this->entity->get_entity_type()->get_bundle_entity_type()) {
            return $this->entity_type_manager->get_storage($bundle_entity_type)->load($this->entity->bundle());
        }
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function form(array $form, Form_State_Interface $form_state)
    {
        if ($this->show_revision_ui()) {
            // Advanced tab must be the first, because other fields rely on that.
            if (!isset($form['advanced'])) {
                $form['advanced'] = ['#type' => 'vertical_tabs', '#weight' => 99];
            }
        }
        $form = parent::form($form, $form_state);
        // Content entity forms do not use the parent's #after_build callback
        // because they only need to rebuild the entity in the validation and the
        // submit handler because Field API uses its own #after_build callback for
        // its widgets.
        unset($form['#after_build']);
        $this->get_form_display($form_state)->build_form($this->entity, $form, $form_state);
        // Allow modules to act before and after form language is updated.
        $form['#entity_builders']['update_form_langcode'] = '::updateFormLangcode';
        if ($this->show_revision_ui()) {
            $this->add_revisionable_form_fields($form);
        }
        $form['footer'] = ['#type' => 'container', '#weight' => 99, '#attributes' => ['class' => ['entity-content-form-footer']], '#optional' => true];
        return $form;
    }
    /**
     * {@inheritdoc}
     */
    public function submit_form(array &$form, Form_State_Interface $form_state): void
    {
        parent::submit_form($form, $form_state);
        // Update the changed timestamp of the entity.
        $this->update_changed_time($this->entity);
    }
    /**
     * {@inheritdoc}
     */
    public function build_entity(array $form, Form_State_Interface $form_state)
    {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entity = parent::build_entity($form, $form_state);
        // Mark the entity as requiring validation.
        $entity->set_validation_required(!$form_state->get_temporary_value('entity_validated'));
        // Save as a new revision if requested to do so.
        if ($this->show_revision_ui() && !$form_state->is_value_empty('revision')) {
            $entity->set_new_revision();
            if ($entity instanceof Revision_Log_Interface) {
                // If a new revision is created, save the current user as
                // revision author.
                $entity->set_revision_user_id($this->current_user()->id());
                $entity->set_revision_creation_time($this->time->get_request_time());
            }
        }
        return $entity;
    }
    /**
     * {@inheritdoc}
     *
     * Button-level validation handlers are highly discouraged for entity forms,
     * as they will prevent entity validation from running. If the entity is going
     * to be saved during the form submission, this method should be manually
     * invoked from the button-level validation handler, otherwise an exception
     * will be thrown.
     */
    public function validate_form(array &$form, Form_State_Interface $form_state)
    {
        parent::validate_form($form, $form_state);
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entity = $this->build_entity($form, $form_state);
        $violations = $entity->validate();
        // Remove violations of inaccessible fields.
        $violations->filter_by_field_access($this->current_user());
        // In case a field-level submit button is clicked, for example the 'Add
        // another item' button for multi-value fields or the 'Upload' button for a
        // File or an Image field, make sure that we only keep violations for that
        // specific field.
        $edited_fields = [];
        if ($limit_validation_errors = $form_state->get_limit_validation_errors()) {
            foreach ($limit_validation_errors as $section) {
                $field_name = reset($section);
                if ($entity->has_field($field_name)) {
                    $edited_fields[] = $field_name;
                }
            }
            $edited_fields = array_unique($edited_fields);
        } else {
            $edited_fields = $this->get_edited_field_names($form_state);
        }
        // Remove violations for fields that are not edited.
        $violations->filter_by_fields(array_diff(array_keys($entity->get_field_definitions()), $edited_fields));
        $this->flag_violations($violations, $form, $form_state);
        // The entity was validated.
        $entity->set_validation_required(false);
        $form_state->set_temporary_value('entity_validated', true);
        return $entity;
    }
    /**
     * Gets the names of all fields edited in the form.
     *
     * If a custom entity form adds some fields to the form (i.e. without using
     * the form display), it needs to add its fields here and override
     * flagViolations() for displaying the violations.
     *
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return string[]
     *   An array of field names.
     */
    protected function get_edited_field_names(Form_State_Interface $form_state): array
    {
        return array_keys($this->get_form_display($form_state)->get_components());
    }
    /**
     * Flags violations for the current form.
     *
     * If a custom entity form adds some fields to the form (i.e. without using
     * the form display), it needs to add its fields to array returned by
     * getEditedFieldNames() and overwrite this method in order to show any
     * violations for those fields; e.g.:
     * @code
     * foreach ($violations->getByField('name') as $violation) {
     *   $form_state->setErrorByName('name', $violation->getMessage());
     * }
     * parent::flagViolations($violations, $form, $form_state);
     * @endcode
     *
     * @param \Drupal\Core\Entity\EntityConstraintViolationListInterface $violations
     *   The violations to flag.
     * @param array $form
     *   A nested array of form elements comprising the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    protected function flag_violations(Entity_Constraint_Violation_List_Interface $violations, array $form, Form_State_Interface $form_state)
    {
        // Flag entity level violations.
        foreach ($violations->get_entity_violations() as $violation) {
            /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
            $form_state->set_error_by_name(str_replace('.', '][', $violation->get_property_path()), $violation->get_message());
        }
        // Let the form display flag violations of its fields.
        $this->get_form_display($form_state)->flag_widgets_errors_from_violations($violations, $form, $form_state);
    }
    /**
     * Initializes the form state and the entity before the first form build.
     *
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    protected function init(Form_State_Interface $form_state)
    {
        // Ensure we act on the translation object corresponding to the current form
        // language.
        $this->init_form_langcodes($form_state);
        $langcode = $this->get_form_langcode($form_state);
        $this->entity = $this->entity->has_translation($langcode) ? $this->entity->get_translation($langcode) : $this->entity->add_translation($langcode);
        $form_display = Entity_Form_Display::collect_render_display($this->entity, $this->get_operation());
        $this->set_form_display($form_display, $form_state);
        parent::init($form_state);
    }
    /**
     * Initializes form language code values.
     *
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    protected function init_form_langcodes(Form_State_Interface $form_state)
    {
        // Store the entity default language to allow checking whether the form is
        // dealing with the original entity or a translation.
        if (!$form_state->has('entity_default_langcode')) {
            $form_state->set('entity_default_langcode', $this->entity->get_untranslated()->language()->get_id());
        }
        // This value might have been explicitly populated to work with a particular
        // entity translation. If not we fall back to the most proper language based
        // on contextual information.
        if (!$form_state->has('langcode')) {
            // Imply a 'view' operation to ensure users edit entities in the same
            // language they are displayed. This allows to keep contextual editing
            // working also for multilingual entities.
            $form_state->set('langcode', $this->entity_repository->get_translation_from_context($this->entity)->language()->get_id());
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_form_langcode(Form_State_Interface $form_state)
    {
        $this->init_form_langcodes($form_state);
        return $form_state->get('langcode');
    }
    /**
     * {@inheritdoc}
     */
    public function is_default_form_langcode(Form_State_Interface $form_state): bool
    {
        $this->init_form_langcodes($form_state);
        return $form_state->get('langcode') == $form_state->get('entity_default_langcode');
    }
    /**
     * {@inheritdoc}
     */
    protected function copy_form_values_to_entity(Entity_Interface $entity, array $form, Form_State_Interface $form_state)
    {
        // First, extract values from widgets.
        $extracted = $this->get_form_display($form_state)->extract_form_values($entity, $form, $form_state);
        // Then extract the values of fields that are not rendered through widgets,
        // by simply copying from top-level form values. This leaves the fields
        // that are not being edited within this form untouched.
        foreach ($form_state->get_values() as $name => $values) {
            if ($entity->has_field($name) && !isset($extracted[$name])) {
                $entity->set($name, $values);
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_form_display(Form_State_Interface $form_state)
    {
        return $form_state->get('form_display');
    }
    /**
     * {@inheritdoc}
     */
    public function set_form_display(Entity_Form_Display_Interface $form_display, Form_State_Interface $form_state): static
    {
        $form_state->set('form_display', $form_display);
        return $this;
    }
    /**
     * Updates the form language to reflect any change to the entity language.
     *
     * There are use cases for modules to act both before and after form language
     * being updated, thus the update is performed through an entity builder
     * callback, which allows to support both cases.
     *
     * @param string $entity_type_id
     *   The entity type identifier.
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity updated with the submitted values.
     * @param array $form
     *   The complete form array.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @see \Drupal\Core\Entity\ContentEntityForm::form()
     */
    public function update_form_langcode($entity_type_id, Entity_Interface $entity, array $form, Form_State_Interface $form_state): void
    {
        $langcode = $entity->language()->get_id();
        $form_state->set('langcode', $langcode);
        // If this is the original entity language, also update the default
        // langcode.
        if ($langcode == $entity->get_untranslated()->language()->get_id()) {
            $form_state->set('entity_default_langcode', $langcode);
        }
    }
    /**
     * Updates the changed time of the entity.
     *
     * Applies only if the entity implements the EntityChangedInterface.
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity updated with the submitted values.
     */
    public function update_changed_time(Entity_Interface $entity): void
    {
        if ($entity instanceof Entity_Changed_Interface) {
            $entity->set_changed_time($this->time->get_request_time());
        }
    }
    /**
     * Add revision form fields if the entity enabled the UI.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     */
    protected function add_revisionable_form_fields(array &$form)
    {
        /** @var ContentEntityTypeInterface $entity_type */
        $entity_type = $this->entity->get_entity_type();
        $new_revision_default = $this->get_new_revision_default();
        // Add a log field if the "Create new revision" option is checked, or if the
        // current user has the ability to check that option.
        $form['revision_information'] = [
            '#type' => 'details',
            '#title' => $this->t('Revision information'),
            // Open by default when "Create new revision" is checked.
            '#open' => $new_revision_default,
            '#group' => 'advanced',
            '#weight' => 20,
            '#optional' => true,
            '#attributes' => ['class' => ['entity-content-form-revision-information']],
            '#attached' => ['library' => ['core/drupal.entity-form']],
        ];
        $form['revision'] = ['#type' => 'checkbox', '#title' => $this->t('Create new revision'), '#default_value' => $new_revision_default, '#access' => !$this->entity->is_new(), '#group' => 'revision_information'];
        // Get log message field's key from definition.
        $log_message_field = $entity_type->get_revision_metadata_key('revision_log_message');
        if ($log_message_field && isset($form[$log_message_field])) {
            $form[$log_message_field] += ['#group' => 'revision_information', '#states' => ['visible' => [':input[name="revision"]' => ['checked' => true]]]];
        }
    }
    /**
     * Should new revisions created on default.
     *
     * @return bool
     *   New revision on default.
     */
    protected function get_new_revision_default()
    {
        $new_revision_default = false;
        $bundle_entity = $this->get_bundle_entity();
        if ($bundle_entity instanceof Revisionable_Entity_Bundle_Interface) {
            // Always use the default revision setting.
            return $bundle_entity->should_create_new_revision();
        }
        return $new_revision_default;
    }
    /**
     * Checks whether the revision form fields should be added to the form.
     *
     * @return bool
     *   TRUE if the form field should be added, FALSE otherwise.
     */
    protected function show_revision_ui()
    {
        return $this->entity->get_entity_type()->show_revision_ui();
    }
}