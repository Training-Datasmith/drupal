<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Discovery;

use Drupal\Component\Plugin\Exception\Plugin_Not_Found_Exception;
/**
 * @see Drupal\Component\Plugin\Discovery\DiscoveryInterface
 */
trait Discovery_Trait
{
    /**
     * {@inheritdoc}
     */
    abstract public function get_definitions();
    /**
     * {@inheritdoc}
     */
    public function get_definition($plugin_id, $exception_on_invalid = true)
    {
        $definitions = $this->get_definitions();
        return $this->do_get_definition($definitions, $plugin_id, $exception_on_invalid);
    }
    /**
     * Gets a specific plugin definition.
     *
     * @param array $definitions
     *   An array of the available plugin definitions.
     * @param string $plugin_id
     *   A plugin id.
     * @param bool $exception_on_invalid
     *   If TRUE, an invalid plugin ID will cause an exception to be thrown; if
     *   FALSE, NULL will be returned.
     *
     * @return array|null
     *   A plugin definition, or NULL if the plugin ID is invalid and
     *   $exception_on_invalid is TRUE.
     *
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     *   Thrown if $plugin_id is invalid and $exception_on_invalid is TRUE.
     */
    protected function do_get_definition(array $definitions, $plugin_id, $exception_on_invalid)
    {
        // Avoid using a ternary that would create a copy of the array.
        if (isset($definitions[$plugin_id])) {
            return $definitions[$plugin_id];
        }
        // Avoid using a ternary that would create a copy of the array.
        if (!$exception_on_invalid) {
            return null;
        }
        $valid_ids = implode(', ', array_keys($definitions));
        throw new Plugin_Not_Found_Exception($plugin_id, sprintf('The "%s" plugin does not exist. Valid plugin IDs for %s are: %s', $plugin_id, static::class, $valid_ids));
    }
    /**
     * {@inheritdoc}
     */
    public function has_definition($plugin_id): bool
    {
        return (bool) $this->get_definition($plugin_id, false);
    }
}