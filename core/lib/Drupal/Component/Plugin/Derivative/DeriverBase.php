<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Derivative;

/**
 * Provides a basic deriver.
 */
abstract class Deriver_Base implements Deriver_Interface
{
    /**
     * List of derivative definitions.
     *
     * @var array
     */
    protected $derivatives = [];
    /**
     * {@inheritdoc}
     */
    public function get_derivative_definition($derivative_id, $base_plugin_definition)
    {
        if (!empty($this->derivatives) && !empty($this->derivatives[$derivative_id])) {
            return $this->derivatives[$derivative_id];
        }
        $this->get_derivative_definitions($base_plugin_definition);
        return $this->derivatives[$derivative_id];
    }
    /**
     * {@inheritdoc}
     */
    public function get_derivative_definitions($base_plugin_definition)
    {
        return $this->derivatives;
    }
}