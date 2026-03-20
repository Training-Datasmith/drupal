<?php

declare (strict_types=1);
namespace Drupal\Core\Display;

/**
 * Provides an interface for variant plugins that are context-aware.
 */
interface Context_Aware_Variant_Interface extends Variant_Interface
{
    /**
     * Gets the values for all defined contexts.
     *
     * @return \Drupal\Component\Plugin\Context\ContextInterface[]
     *   An array of set contexts, keyed by context name.
     */
    public function get_contexts();
    /**
     * Sets the context values for this display variant.
     *
     * @param \Drupal\Component\Plugin\Context\ContextInterface[] $contexts
     *   An array of contexts, keyed by context name.
     *
     * @return $this
     */
    public function set_contexts(array $contexts);
}