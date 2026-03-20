<?php

declare(strict_types=1);

namespace Drupal\language\Form;

use Drupal\Core\Form\ConfigFormBaseTrait;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * The confirmation form for deleting a browser language negotiation mapping.
 *
 * @internal
 */
class NegotiationBrowserDeleteForm extends ConfirmFormBase
{
    use ConfigFormBaseTrait;

    /**
     * The browser language code to be deleted.
     *
     * @var string
     */
    protected $browserLangcode;

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames(): array
    {
        return ['language.mappings'];
    }

    /**
     * {@inheritdoc}
     */
    public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Are you sure you want to delete %browser_langcode?', ['%browser_langcode' => $this->browserLangcode]);
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelUrl(): \Drupal\Core\Url
    {
        return new Url('language.negotiation_browser');
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'language_negotiation_configure_browser_delete_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $browser_langcode = null)
    {
        $this->browserLangcode = $browser_langcode;

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $this->config('language.mappings')
          ->clear('map.' . $this->browserLangcode)
          ->save();

        $args = [
          '%browser' => $this->browserLangcode,
        ];

        $this->logger('language')->notice('The browser language detection mapping for the %browser browser language code has been deleted.', $args);

        $this->messenger()->addStatus($this->t('The mapping for the %browser browser language code has been deleted.', $args));

        $form_state->setRedirect('language.negotiation_browser');
    }

}
