<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Action;

/**
 * @internal
 *   This API is experimental.
 */
interface Config_Action_Plugin_Interface
{
    /**
     * Applies the config action.
     *
     * @param string $configName
     *   The name of the config to apply the action to.
     * @param mixed $value
     *   The value for the action to use.
     *
     * @throws ConfigActionException
     */
    public function apply(string $config_name, mixed $value): void;
}