<?php

declare(strict_types=1);

namespace Drupal\node\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for rebuilding permissions.
 *
 * @internal
 */
class RebuildPermissionsForm extends ConfirmFormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'node_configure_rebuild_confirm';
    }

    /**
     * {@inheritdoc}
     */
    public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Are you sure you want to rebuild the permissions on site content?');
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelUrl(): \Drupal\Core\Url
    {
        return new Url('system.status');
    }

    /**
     * {@inheritdoc}
     */
    public function getConfirmText(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Rebuild permissions');
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('This action rebuilds all permissions on site content, and may be a lengthy process. This action cannot be undone.');
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        node_access_rebuild(true);
        $form_state->setRedirectUrl($this->getCancelUrl());
    }

}
