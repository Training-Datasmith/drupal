<?php

declare(strict_types=1);

namespace Drupal\taxonomy\Form;

use Drupal\Core\Entity\EntityDeleteForm;

/**
 * Provides a deletion confirmation form for taxonomy vocabulary.
 *
 * @internal
 */
class VocabularyDeleteForm extends EntityDeleteForm
{
    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'taxonomy_vocabulary_confirm_delete';
    }

    /**
     * {@inheritdoc}
     */
    public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Are you sure you want to delete the vocabulary %title?', ['%title' => $this->entity->label()]);
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Deleting a vocabulary will delete all the terms in it. This action cannot be undone.');
    }

    /**
     * {@inheritdoc}
     */
    protected function getDeletionMessage(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Deleted vocabulary %name.', ['%name' => $this->entity->label()]);
    }

}
