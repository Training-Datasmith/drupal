<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

use Drupal\Core\Form\Form_State_Interface;
/**
 * Provides a helper to for submitting an AJAX form.
 *
 * @internal
 */
trait Ajax_Form_Helper_Trait
{
    use Ajax_Helper_Trait;
    /**
     * Submit form dialog #ajax callback.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     *   An AJAX response that display validation error messages or represents a
     *   successful submission.
     */
    public function ajax_submit(array &$form, Form_State_Interface $form_state)
    {
        if ($form_state->has_any_errors()) {
            $form['status_messages'] = ['#type' => 'status_messages', '#weight' => -1000];
            $form['#sorted'] = false;
            $response = new Ajax_Response();
            $response->add_command(new Replace_Command('[data-drupal-selector="' . $form['#attributes']['data-drupal-selector'] . '"]', $form));
        } else {
            $response = $this->successful_ajax_submit($form, $form_state);
        }
        return $response;
    }
    /**
     * Allows the form to respond to a successful AJAX submission.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return \Drupal\Core\Ajax\AjaxResponse
     *   An AJAX response.
     */
    abstract protected function successful_ajax_submit(array $form, Form_State_Interface $form_state);
}