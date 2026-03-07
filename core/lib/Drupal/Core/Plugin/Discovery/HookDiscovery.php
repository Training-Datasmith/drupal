<?php

declare(strict_types=1);

namespace Drupal\Core\Plugin\Discovery;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryTrait;

/**
 * Provides a hook-based plugin discovery class.
 */
class HookDiscovery implements DiscoveryInterface
{
    use DiscoveryTrait;

    /**
     * Constructs a Drupal\Core\Plugin\Discovery\HookDiscovery object.
     *
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
     *   The module handler.
     * @param string $hook
     *   The Drupal hook that a module can implement in order to interface to
     *   this discovery class.
     */
    public function __construct(
        protected \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler,
        /**
         * The name of the hook that will be implemented by this discovery instance.
         */
        protected $hook
    ) {
    }

    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function getDefinitions(): array
    {
        $definitions = [];
        $this->moduleHandler->invokeAllWith($this->hook, function (callable $hook, string $module) use (&$definitions): void {
            $module_definitions = $hook();
            foreach ($module_definitions as $plugin_id => $definition) {
                $definition['provider'] = $module;
                $definitions[$plugin_id] = $definition;
            }
        });
        return $definitions;
    }

}
