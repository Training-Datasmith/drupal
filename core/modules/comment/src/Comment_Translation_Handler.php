<?php

declare(strict_types=1);

namespace Drupal\comment;

use Drupal\content_translation\ContentTranslationHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the translation handler for comments.
 */
class CommentTranslationHandler extends ContentTranslationHandler
{
    /**
     * {@inheritdoc}
     */
    public function entityFormAlter(array &$form, FormStateInterface $form_state, EntityInterface $entity): void
    {
        parent::entityFormAlter($form, $form_state, $entity);

        if (isset($form['content_translation'])) {
            // We do not need to show these values on comment forms: they inherit the
            // basic comment property values.
            $form['content_translation']['status']['#access'] = false;
            $form['content_translation']['name']['#access'] = false;
            $form['content_translation']['created']['#access'] = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function entityFormTitle(EntityInterface $entity): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Edit comment @subject', ['@subject' => $entity->label()]);
    }

    /**
     * {@inheritdoc}
     */
    public function entityFormEntityBuild($entity_type, EntityInterface $entity, array $form, FormStateInterface $form_state): void
    {
        if ($form_state->hasValue('content_translation')) {
            $translation = &$form_state->getValue('content_translation');
            /** @var \Drupal\comment\CommentInterface $entity */
            $translation['status'] = $entity->isPublished();
            $translation['name'] = $entity->getAuthorName();
        }
        parent::entityFormEntityBuild($entity_type, $entity, $form, $form_state);
    }

}
