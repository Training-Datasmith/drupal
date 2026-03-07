<?php

declare(strict_types=1);

namespace Drupal\config_translation\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Defines a form for adding configuration translations.
 *
 * @internal
 */
class ConfigTranslationAddForm extends ConfigTranslationFormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'config_translation_add_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, ?RouteMatchInterface $route_match = null, $plugin_id = null, $langcode = null)
    {
        $form = parent::buildForm($form, $form_state, $route_match, $plugin_id, $langcode);
        $form['#title'] = $this->t('Add @language translation for %label', [
          '%label' => $this->mapper->getTitle(),
          '@language' => $this->language->getName(),
        ]);
        return $form;
    }

}
