<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Discovery;

/**
 * Trait for accessing cached definitions of the plugin discovery component.
 */
trait Discovery_Cached_Trait
{
    use Discovery_Trait;
    /**
     * Cached definitions array.
     *
     * @var array
     */
    protected $definitions;
    /**
     * {@inheritdoc}
     */
    public function get_definition($plugin_id, $exception_on_invalid = true)
    {
        // Fetch definitions if they're not loaded yet.
        if (!isset($this->definitions)) {
            $this->get_definitions();
        }
        return $this->do_get_definition($this->definitions, $plugin_id, $exception_on_invalid);
    }
}