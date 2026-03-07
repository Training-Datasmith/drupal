<?php

declare(strict_types=1);

namespace Drupal\content_moderation\Entity\Handler;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Customizations for block content entities.
 *
 * @internal
 */
class BlockContentModerationHandler extends ModerationHandler
{
    /**
     * {@inheritdoc}
     */
    public function enforceRevisionsEntityFormAlter(array &$form, FormStateInterface $form_state, $form_id): void
    {
        $form['revision']['#default_value'] = true;
        $form['revision']['#disabled'] = true;
        $form['revision']['#description'] = $this->t('Revisions must be required when moderation is enabled.');
    }

    /**
     * {@inheritdoc}
     */
    public function enforceRevisionsBundleFormAlter(array &$form, FormStateInterface $form_state, $form_id): void
    {
        $form['revision']['#default_value'] = 1;
        $form['revision']['#disabled'] = true;
        $form['revision']['#description'] = $this->t('Revisions must be required when moderation is enabled.');
    }

    /**
     * {@inheritdoc}
     */
    public function isModeratedEntity(ContentEntityInterface $entity)
    {
        // Only reusable blocks can be moderated individually. Non-reusable or
        // inline blocks are moderated as part of the entity they are a composite
        // of.
        return $entity->isReusable();
    }

}
