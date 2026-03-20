<?php

declare (strict_types=1);
namespace Drupal\Core\Discovery;

use Drupal\Component\Discovery\Yaml_Discovery as ComponentYamlDiscovery;
use Drupal\Component\Serialization\Exception\Invalid_Data_Type_Exception;
use Drupal\Core\Serialization\Yaml;
/**
 * Provides discovery for YAML files within a given set of directories.
 *
 * This overrides the Component file decoding with the Core YAML implementation.
 */
class Yaml_Discovery extends Component_Yaml_Discovery
{
    /**
     * {@inheritdoc}
     */
    protected function decode($file)
    {
        try {
            return Yaml::decode(file_get_contents($file)) ?: [];
        } catch (Invalid_Data_Type_Exception $e) {
            throw new Invalid_Data_Type_Exception($file . ': ' . $e->get_message(), $e->get_code(), $e);
        }
    }
}