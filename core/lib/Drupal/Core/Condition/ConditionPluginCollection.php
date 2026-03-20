<?php

declare (strict_types=1);
namespace Drupal\Core\Condition;

use Drupal\Component\Plugin\Context\Context_Interface;
use Drupal\Core\Plugin\Default_Lazy_Plugin_Collection;
/**
 * Provides a collection of condition plugins.
 */
class Condition_Plugin_Collection extends Default_Lazy_Plugin_Collection
{
    /**
     * An array of collected contexts for conditions.
     *
     * @var \Drupal\Component\Plugin\Context\ContextInterface[]
     */
    protected $condition_contexts = [];
    /**
     * {@inheritdoc}
     *
     * @return \Drupal\Core\Condition\ConditionInterface
     *   The condition plugin instance for the given instance ID.
     */
    public function &get($instance_id)
    {
        return parent::get($instance_id);
    }
    /**
     * {@inheritdoc}
     */
    public function get_configuration()
    {
        $configuration = parent::get_configuration();
        // Remove configuration if it matches the defaults.
        foreach ($configuration as $instance_id => $instance_config) {
            $default_config = [];
            $default_config['id'] = $instance_id;
            $default_config += $this->get($instance_id)->default_configuration();
            // In order to determine if a plugin is configured, we must compare it to
            // its default configuration. The default configuration of a plugin does
            // not contain context_mapping and it is not used when the plugin is not
            // configured, so remove the context_mapping from the instance config to
            // compare the remaining values.
            unset($instance_config['context_mapping']);
            ksort($default_config);
            ksort($instance_config);
            // With PHP 8 type juggling, there should not be an issue using equal
            // operator instead of identical operator. Allowing looser comparison here
            // will prevent configuration from being erroneously exported when values
            // are updated via form elements that return values of the wrong type, for
            // example, '0'/'1' vs FALSE/TRUE.
            if ($default_config == $instance_config) {
                unset($configuration[$instance_id]);
            }
        }
        return $configuration;
    }
    /**
     * Sets the condition context for a given name.
     *
     * @param string $name
     *   The name of the context.
     * @param \Drupal\Component\Plugin\Context\ContextInterface $context
     *   The context to add.
     *
     * @return $this
     */
    public function add_context($name, Context_Interface $context): static
    {
        $this->condition_contexts[$name] = $context;
        return $this;
    }
    /**
     * Gets the values for all defined contexts.
     *
     * @return \Drupal\Component\Plugin\Context\ContextInterface[]
     *   An array of set contexts, keyed by context name.
     */
    public function get_condition_contexts()
    {
        return $this->condition_contexts;
    }
}