<?php

declare(strict_types=1);

namespace Drupal\search\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides the search reindex confirmation form.
 *
 * @internal
 */
class ReindexConfirm extends ConfirmFormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'search_reindex_confirm';
    }

    /**
     * {@inheritdoc}
     */
    public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Are you sure you want to re-index the site?');
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t("This will re-index content in the search indexes of all active search pages. Searching will continue to work, but new content won't be indexed until all existing content has been re-indexed. This action cannot be undone.");
    }

    /**
     * {@inheritdoc}
     */
    public function getConfirmText(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Re-index site');
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelText(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Cancel');
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelUrl(): \Drupal\Core\Url
    {
        return new Url('entity.search_page.collection');
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        if ($form['confirm']) {
            // Ask each active search page to mark itself for re-index.
            $search_page_repository = \Drupal::service('search.search_page_repository');
            foreach ($search_page_repository->getIndexableSearchPages() as $entity) {
                $entity->getPlugin()->markForReindex();
            }
            $this->messenger()->addStatus($this->t('All search indexes will be rebuilt.'));
            $form_state->setRedirectUrl($this->getCancelUrl());
        }
    }

}
