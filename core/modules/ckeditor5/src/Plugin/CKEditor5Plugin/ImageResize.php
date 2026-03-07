<?php

declare(strict_types=1);

namespace Drupal\ckeditor5\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * CKEditor 5 ImageResize plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class ImageResize extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['allow_resize' => TRUE];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['allow_resize'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow the user to resize images'),
      '#default_value' => $this->configuration['allow_resize'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Match the config schema structure at
    // ckeditor5.plugin.ckeditor5_imageResize.
    $form_value = $form_state->getValue('allow_resize');
    $form_state->setValue('allow_resize', (bool) $form_value);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['allow_resize'] = $form_state->getValue('allow_resize');
  }

}
