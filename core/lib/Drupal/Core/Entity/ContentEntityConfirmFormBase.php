<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

use Drupal\Core\Form\Confirm_Form_Helper;
use Drupal\Core\Form\Confirm_Form_Interface;
use Drupal\Core\Form\Form_State_Interface;
/**
 * Provides a generic base class for an entity-based confirmation form.
 */
abstract class Content_Entity_Confirm_Form_Base extends Content_Entity_Form implements Confirm_Form_Interface
{
    /**
     * {@inheritdoc}
     */
    public function get_base_form_id()
    {
        return $this->entity->get_entity_type_id() . '_confirm_form';
    }
    /**
     * {@inheritdoc}
     */
    public function get_description()
    {
        return $this->t('This action cannot be undone.');
    }
    /**
     * {@inheritdoc}
     */
    public function get_confirm_text()
    {
        return $this->t('Confirm');
    }
    /**
     * {@inheritdoc}
     */
    public function get_cancel_text()
    {
        return $this->t('Cancel');
    }
    /**
     * {@inheritdoc}
     */
    public function get_form_name()
    {
        return 'confirm';
    }
    /**
     * {@inheritdoc}
     */
    public function build_form(array $form, Form_State_Interface $form_state)
    {
        $form = parent::build_form($form, $form_state);
        $form['#title'] = $this->get_question();
        $form['#attributes']['class'][] = 'confirmation';
        $form['description'] = ['#markup' => $this->get_description()];
        $form[$this->get_form_name()] = ['#type' => 'hidden', '#value' => 1];
        // By default, render the form using theme_confirm_form().
        if (!isset($form['#theme'])) {
            $form['#theme'] = 'confirm_form';
        }
        return $form;
    }
    /**
     * {@inheritdoc}
     */
    public function form(array $form, Form_State_Interface $form_state)
    {
        // Do not attach fields to the confirm form.
        return $form;
    }
    /**
     * {@inheritdoc}
     */
    protected function actions(array $form, Form_State_Interface $form_state)
    {
        return ['submit' => ['#type' => 'submit', '#value' => $this->get_confirm_text(), '#submit' => [$this->submit_form(...)]], 'cancel' => Confirm_Form_Helper::build_cancel_link($this, $this->get_request())];
    }
    /**
     * {@inheritdoc}
     *
     * The save() method is not used in ContentEntityConfirmFormBase. This
     * overrides the default implementation that saves the entity.
     *
     * Confirmation forms should override submitForm() instead for their logic.
     */
    public function save(array $form, Form_State_Interface $form_state)
    {
    }
    /**
     * {@inheritdoc}
     *
     * The delete() method is not used in ContentEntityConfirmFormBase. This
     * overrides the default implementation that redirects to the delete-form
     * confirmation form.
     *
     * Confirmation forms should override submitForm() instead for their logic.
     */
    public function delete(array $form, Form_State_Interface $form_state)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function validate_form(array &$form, Form_State_Interface $form_state)
    {
        // Override the default validation implementation as it is not necessary
        // nor possible to validate an entity in a confirmation form.
        return $this->entity;
    }
}