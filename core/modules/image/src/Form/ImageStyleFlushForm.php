<?php

namespace Drupal\image\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for image style flush.
 *
 * @internal
 */
class ImageStyleFlushForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('Are you sure you want to apply the updated %name image effect to all images?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('This operation does not change the original images but the copies created for this style will be recreated.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('Flush');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->entity->toUrl('collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->entity->flush();
    $this->messenger()->addStatus($this->t('The image style %name has been flushed.', ['%name' => $this->entity->label()]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
