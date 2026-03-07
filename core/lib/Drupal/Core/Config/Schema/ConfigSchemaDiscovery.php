<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Schema;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryTrait;

/**
 * Allows YAML files to define config schema types.
 */
class ConfigSchemaDiscovery implements DiscoveryInterface
{
    use DiscoveryTrait;

    /**
     * Constructs a ConfigSchemaDiscovery object.
     *
     * @param \Drupal\Core\Config\StorageInterface $schemaStorage
     *   The storage object to use for reading schema data.
     */
    public function __construct(protected \Drupal\Core\Config\StorageInterface $schemaStorage)
    {
    }

    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function getDefinitions(): array
    {
        $definitions = [];
        foreach ($this->schemaStorage->readMultiple($this->schemaStorage->listAll()) as $schema) {
            foreach ($schema as $type => $definition) {
                $definitions[$type] = $definition;
            }
        }
        return $definitions;
    }

}
