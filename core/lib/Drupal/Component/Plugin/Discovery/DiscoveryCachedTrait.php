<?php

declare(strict_types=1);

namespace Drupal\Component\Plugin\Discovery;

/**
 * Trait for accessing cached definitions of the plugin discovery component.
 */
trait DiscoveryCachedTrait
{
    use DiscoveryTrait;

    /**
     * Cached definitions array.
     *
     * @var array
     */
    protected $definitions;

    /**
     * {@inheritdoc}
     */
    public function getDefinition($plugin_id, $exception_on_invalid = true)
    {
        // Fetch definitions if they're not loaded yet.
        if (!isset($this->definitions)) {
            $this->getDefinitions();
        }

        return $this->doGetDefinition($this->definitions, $plugin_id, $exception_on_invalid);
    }

}
