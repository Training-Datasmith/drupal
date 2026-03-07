<?php

namespace Drupal\content_moderation\Plugin\Field\FieldFormatter;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'content_moderation_state' formatter.
 */
#[FieldFormatter(
  id: 'content_moderation_state',
  label: new TranslatableMarkup('Content moderation state'),
  field_types: [
    'string',
  ],
)]
class ContentModerationStateFormatter extends FormatterBase {

  /**
   * Create an instance of ContentModerationStateFormatter.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, /**
   * The moderation information service.
   */
  protected \Drupal\content_moderation\ModerationInformationInterface $moderationInformation) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   * @return array{'#markup': mixed}[]
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $workflow = $this->moderationInformation->getWorkflowForEntity($items->getEntity());
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#markup' => $workflow->getTypePlugin()->getState($item->value)->label(),
      ];
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'moderation_state' && $field_definition->getTargetEntityTypeId() !== 'content_moderation_state';
  }

}
