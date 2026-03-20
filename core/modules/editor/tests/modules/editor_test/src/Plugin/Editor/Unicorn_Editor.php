<?php

declare(strict_types=1);

namespace Drupal\editor_test\Plugin\Editor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\editor\Attribute\Editor;
use Drupal\editor\Entity\Editor as EditorEntity;
use Drupal\editor\Plugin\EditorBase;

/**
 * Defines a Unicorn-powered text editor for Drupal (for testing purposes).
 */
#[Editor(
    id: 'unicorn',
    label: new TranslatableMarkup('Unicorn Editor'),
    supports_content_filtering: true,
    supports_inline_editing: true,
    is_xss_safe: false,
    supported_element_types: [
    'textarea',
    'textfield',
  ]
)]
class UnicornEditor extends EditorBase
{
    /**
     * {@inheritdoc}
     */
    public function getDefaultSettings()
    {
        return ['ponies_too' => true];
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form['ponies_too'] = [
          '#title' => $this->t('Pony mode'),
          '#type' => 'checkbox',
          '#default_value' => true,
        ];
        $form_state->loadInclude('editor', 'admin.inc');
        $form['image_upload'] = editor_image_upload_settings_form($form_state->get('editor'));
        $form['image_upload']['#element_validate'][] = [$this, 'validateImageUploadSettings'];
        return $form;
    }

    /**
     * Render API callback: Image upload handler for confirmation form.
     *
     * This function is assigned as a #element_validate callback.
     *
     * Moves the text editor's image upload settings into $editor->image_upload.
     *
     * @see editor_image_upload_settings_form()
     */
    public function validateImageUploadSettings(array $element, FormStateInterface $form_state)
    {
        $settings = &$form_state->getValue(['editor', 'settings', 'image_upload']);
        $form_state->get('editor')->setImageUploadSettings($settings);
        $form_state->unsetValue(['editor', 'settings', 'image_upload']);
    }

    /**
     * {@inheritdoc}
     */
    public function getJSSettings(EditorEntity $editor)
    {
        $js_settings = [];
        $settings = $editor->getSettings();
        if ($settings['ponies_too']) {
            $js_settings['ponyModeEnabled'] = true;
        }
        return $js_settings;
    }

    /**
     * {@inheritdoc}
     */
    public function getLibraries(EditorEntity $editor)
    {
        return [
          'editor_test/unicorn',
        ];
    }

}
