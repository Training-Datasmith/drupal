<?php

namespace Drupal\filter\Plugin\DataType;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\Core\TypedData\Plugin\DataType\StringData;

/**
 * The filter format data type.
 */
#[DataType(
  id: "filter_format",
  label: new TranslatableMarkup("Filter format"),
)]
class FilterFormat extends StringData implements OptionsProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getPossibleValues(?AccountInterface $account = NULL): array {
    return array_keys($this->getPossibleOptions($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleOptions(?AccountInterface $account = NULL): array {
    return array_map(fn($format) => $format->label(), filter_formats());
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableValues(?AccountInterface $account = NULL): array {
    return array_keys($this->getSettableOptions($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableOptions(?AccountInterface $account = NULL): array {
    // @todo Avoid calling functions but move to injected dependencies.
    return array_map(fn($format) => $format->label(), filter_formats($account));
  }

}
