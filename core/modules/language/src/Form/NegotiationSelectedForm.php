<?php

declare(strict_types=1);

namespace Drupal\language\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Configure the selected language negotiation method for this site.
 *
 * @internal
 */
class NegotiationSelectedForm extends ConfigFormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'language_negotiation_configure_selected_form';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames(): array
    {
        return ['language.negotiation'];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('language.negotiation');
        $form['selected_langcode'] = [
          '#type' => 'language_select',
          '#title' => $this->t('Language'),
          '#languages' => LanguageInterface::STATE_CONFIGURABLE | LanguageInterface::STATE_SITE_DEFAULT,
          '#default_value' => $config->get('selected_langcode'),
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $this->config('language.negotiation')
          ->set('selected_langcode', $form_state->getValue('selected_langcode'))
          ->save();

        parent::submitForm($form, $form_state);
    }

}
