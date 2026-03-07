<?php

namespace Drupal\Component\Plugin;

/**
 * Base class for plugins wishing to support metadata inspection.
 */
abstract class PluginBase implements PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * A string which is used to separate base plugin IDs from the derivative ID.
   */
  const DERIVATIVE_SEPARATOR = ':';

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin ID for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   */
  public function __construct(
      protected array $configuration,
      /**
       * The plugin ID.
       */
      protected $pluginId,
      /**
       * The plugin implementation definition.
       */
      protected $pluginDefinition
  )
  {
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseId() {
    $plugin_id = $this->getPluginId();
    if (strpos($plugin_id, (string) static::DERIVATIVE_SEPARATOR)) {
      [$plugin_id] = explode(static::DERIVATIVE_SEPARATOR, $plugin_id, 2);
    }
    return $plugin_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId() {
    $plugin_id = $this->getPluginId();
    $derivative_id = NULL;
    if (strpos($plugin_id, (string) static::DERIVATIVE_SEPARATOR)) {
      [, $derivative_id] = explode(static::DERIVATIVE_SEPARATOR, $plugin_id, 2);
    }
    return $derivative_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

}
