<?php

namespace Drupal\content_moderation\Entity\Handler;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Customizations for node entities.
 *
 * @internal
 */
class NodeModerationHandler extends ModerationHandler {

  /**
   * NodeModerationHandler constructor.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInfo
   *   The moderation information service.
   */
  public function __construct(protected \Drupal\content_moderation\ModerationInformationInterface $moderationInfo)
  {
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function enforceRevisionsEntityFormAlter(array &$form, FormStateInterface $form_state, $form_id): void {
    $form['revision']['#disabled'] = TRUE;
    $form['revision']['#default_value'] = TRUE;
    $form['revision']['#description'] = $this->t('Revisions are required.');
  }

  /**
   * {@inheritdoc}
   */
  public function enforceRevisionsBundleFormAlter(array &$form, FormStateInterface $form_state, $form_id): void {
    // Force the revision checkbox on.
    $form['workflow']['options']['revision']['#value'] = 'revision';
    $form['workflow']['options']['revision']['#disabled'] = TRUE;
  }

}
