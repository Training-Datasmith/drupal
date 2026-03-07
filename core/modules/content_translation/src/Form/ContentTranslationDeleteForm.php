<?php

declare(strict_types=1);

namespace Drupal\content_translation\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Delete translation form for content_translation module.
 *
 * @internal
 */
class ContentTranslationDeleteForm extends ContentEntityDeleteForm
{
    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'content_translation_delete_confirm';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, ?LanguageInterface $language = null)
    {
        if ($language) {
            $form_state->set('langcode', $language->getId());
        }
        return parent::buildForm($form, $form_state);
    }

}
