<?php

namespace Drupal\Core\ProxyBuilder;

use Drupal\Component\ProxyBuilder\ProxyBuilder as BaseProxyBuilder;

/**
 * Extend the component proxy builder by using the DependencySerializationTrait.
 */
class ProxyBuilder extends BaseProxyBuilder {

  /**
   * {@inheritdoc}
   */
  protected function buildUseStatements(): string {
    $output = parent::buildUseStatements();

    return $output . ('use \Drupal\Core\DependencyInjection\DependencySerializationTrait;' . "\n\n");
  }

}
