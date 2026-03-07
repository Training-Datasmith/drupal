<?php

namespace Drupal\image\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\image\ConfigurableImageEffectBase;

/**
 * Converts an image resource.
 */
#[ImageEffect(
  id: "image_convert",
  label: new TranslatableMarkup("Convert"),
  description: new TranslatableMarkup("Converts an image to a format (such as JPEG)."),
)]
class ConvertImageEffect extends ConfigurableImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image): bool {
    if (!$image->convert($this->configuration['extension'])) {
      $this->logger->error('Image convert failed using the %toolkit toolkit on %path (%mimetype)', [
        '%toolkit' => $image->getToolkitId(),
        '%path' => $image->getSource(),
        '%mimetype' => $image->getMimeType(),
      ]);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeExtension($extension) {
    return $this->configuration['extension'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summary = [
      '#markup' => mb_strtoupper((string) $this->configuration['extension']),
    ];

    return $summary + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'extension' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $extensions = \Drupal::service('image.toolkit.manager')->getDefaultToolkit()->getSupportedExtensions();
    $options = array_combine(
      $extensions,
      array_map(mb_strtoupper(...), $extensions)
    );
    $form['extension'] = [
      '#type' => 'select',
      '#title' => $this->t('Convert to'),
      '#default_value' => $this->configuration['extension'],
      '#required' => TRUE,
      '#options' => $options,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['extension'] = $form_state->getValue('extension');
  }

}
