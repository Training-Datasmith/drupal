<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

use Drupal\Core\Entity\Display\Entity_Form_Display_Interface;
use Drupal\Core\Form\Form_State_Interface;
/**
 * Defines a common interface for content entity form classes.
 */
interface Content_Entity_Form_Interface extends Entity_Form_Interface
{
    /**
     * Gets the form display.
     *
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
     *   The current form display.
     */
    public function get_form_display(Form_State_Interface $form_state);
    /**
     * Sets the form display.
     *
     * Sets the form display which will be used for populating form element
     * defaults.
     *
     * @param \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display
     *   The form display that the current form operates with.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return $this
     */
    public function set_form_display(Entity_Form_Display_Interface $form_display, Form_State_Interface $form_state);
    /**
     * Gets the code identifying the active form language.
     *
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return string
     *   The form language code.
     */
    public function get_form_langcode(Form_State_Interface $form_state);
    /**
     * Checks whether the current form language matches the entity one.
     *
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return bool
     *   Returns TRUE if the entity form language matches the entity one.
     */
    public function is_default_form_langcode(Form_State_Interface $form_state);
    /**
     * {@inheritdoc}
     *
     * Note that extending classes should not override this method to add entity
     * validation logic, but define further validation constraints using the
     * entity validation API and/or provide a new validation constraint if
     * necessary. This is the only way to ensure that the validation logic
     * is correctly applied independently of form submissions; e.g., for REST
     * requests.
     * For more information about entity validation, see
     * https://www.drupal.org/node/2015613.
     *
     * @return \Drupal\Core\Entity\ContentEntityInterface
     *   The built entity.
     */
    public function validate_form(array &$form, Form_State_Interface $form_state);
}