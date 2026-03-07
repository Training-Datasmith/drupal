<?php

declare(strict_types=1);

namespace Drupal\editor_test\Plugin\Editor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\editor\Attribute\Editor;
use Drupal\editor\Entity\Editor as EditorEntity;
use Drupal\editor\Plugin\EditorBase;

/**
 * Defines a Tyrannosaurus-Rex powered text editor for testing purposes.
 */
#[Editor(
    id: 'trex',
    label: new TranslatableMarkup('TRex Editor'),
    supports_content_filtering: true,
    supports_inline_editing: true,
    is_xss_safe: false,
    supported_element_types: [
    'textarea',
  ]
)]
class TRexEditor extends EditorBase
{
    /**
     * {@inheritdoc}
     */
    public function getDefaultSettings()
    {
        return ['stumpy_arms' => true];
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form['stumpy_arms'] = [
          '#title' => $this->t('Stumpy arms'),
          '#type' => 'checkbox',
          '#default_value' => true,
        ];
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function getJSSettings(EditorEntity $editor)
    {
        $js_settings = [];
        $settings = $editor->getSettings();
        if ($settings['stumpy_arms']) {
            $js_settings['doMyArmsLookStumpy'] = true;
        }
        return $js_settings;
    }

    /**
     * {@inheritdoc}
     */
    public function getLibraries(EditorEntity $editor)
    {
        return [
          'editor_test/trex',
        ];
    }

}
