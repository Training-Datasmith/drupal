<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

use Drupal\Core\Form\Form_State_Interface;
/**
 * Provides a generic base class for a content entity deletion form.
 *
 * @todo Re-evaluate and streamline the entity deletion form class hierarchy in
 *   https://www.drupal.org/node/2491057.
 */
class Content_Entity_Delete_Form extends Content_Entity_Confirm_Form_Base
{
    use Entity_Delete_Form_Trait {
        getQuestion as traitGetQuestion;
        logDeletionMessage as traitLogDeletionMessage;
        getDeletionMessage as traitGetDeletionMessage;
        getCancelUrl as traitGetCancelUrl;
    }
    /**
     * {@inheritdoc}
     */
    public function build_form(array $form, Form_State_Interface $form_state)
    {
        $form = parent::build_form($form, $form_state);
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entity = $this->get_entity();
        if ($entity->is_default_translation()) {
            if (count($entity->get_translation_languages()) > 1) {
                $languages = [];
                foreach ($entity->get_translation_languages() as $language) {
                    $languages[] = $language->get_name();
                }
                $form['deleted_translations'] = ['#theme' => 'item_list', '#title' => $this->t('The following @entity-type translations will be deleted:', ['@entity-type' => $entity->get_entity_type()->get_singular_label()]), '#items' => $languages];
                $form['actions']['submit']['#value'] = $this->t('Delete all translations');
            }
        } else {
            $form['actions']['submit']['#value'] = $this->t('Delete @language translation', ['@language' => $entity->language()->get_name()]);
        }
        return $form;
    }
    /**
     * {@inheritdoc}
     */
    public function submit_form(array &$form, Form_State_Interface $form_state): void
    {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entity = $this->get_entity();
        $message = $this->get_deletion_message();
        // Make sure that deleting a translation does not delete the whole entity.
        if (!$entity->is_default_translation()) {
            $untranslated_entity = $entity->get_untranslated();
            $untranslated_entity->remove_translation($entity->language()->get_id());
            $untranslated_entity->save();
            $form_state->set_redirect_url($untranslated_entity->to_url('canonical'));
        } else {
            $entity->delete();
            $form_state->set_redirect_url($this->get_redirect_url());
        }
        $this->messenger()->add_status($message);
        $this->log_deletion_message();
    }
    /**
     * {@inheritdoc}
     */
    public function get_cancel_url()
    {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entity = $this->get_entity();
        return $entity->is_default_translation() ? $this->trait_get_cancel_url() : $entity->to_url('canonical');
    }
    /**
     * {@inheritdoc}
     */
    protected function get_deletion_message()
    {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entity = $this->get_entity();
        if (!$entity->is_default_translation()) {
            return $this->t('The @entity-type %label @language translation has been deleted.', ['@entity-type' => $entity->get_entity_type()->get_singular_label(), '%label' => $entity->label(), '@language' => $entity->language()->get_name()]);
        }
        return $this->trait_get_deletion_message();
    }
    /**
     * {@inheritdoc}
     */
    protected function log_deletion_message()
    {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entity = $this->get_entity();
        if (!$entity->is_default_translation()) {
            $this->logger($entity->get_entity_type()->get_provider())->info('The @entity-type %label @language translation has been deleted.', ['@entity-type' => $entity->get_entity_type()->get_singular_label(), '%label' => $entity->label(), '@language' => $entity->language()->get_name()]);
        } else {
            $this->trait_log_deletion_message();
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_question()
    {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entity = $this->get_entity();
        if (!$entity->is_default_translation()) {
            return $this->t('Are you sure you want to delete the @language translation of the @entity-type %label?', ['@language' => $entity->language()->get_name(), '@entity-type' => $this->get_entity()->get_entity_type()->get_singular_label(), '%label' => $this->get_entity()->label()]);
        }
        return $this->trait_get_question();
    }
}