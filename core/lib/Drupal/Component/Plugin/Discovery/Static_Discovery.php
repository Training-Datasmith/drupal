<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Discovery;

/**
 * Allows plugin definitions to be manually registered.
 */
class Static_Discovery implements Discovery_Interface
{
    use Discovery_Cached_Trait;
    /**
     * {@inheritdoc}
     */
    public function get_definitions()
    {
        if (!$this->definitions) {
            $this->definitions = [];
        }
        return $this->definitions;
    }
    /**
     * Sets a plugin definition.
     */
    public function set_definition($plugin, $definition): void
    {
        $this->definitions[$plugin] = $definition;
    }
    /**
     * Deletes a plugin definition.
     */
    public function delete_definition($plugin): void
    {
        unset($this->definitions[$plugin]);
    }
}