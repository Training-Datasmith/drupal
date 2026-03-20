<?php

declare (strict_types=1);
namespace Drupal\Component\Annotation;

/**
 * Defines a common interface for classed annotations.
 */
interface Annotation_Interface
{
    /**
     * Gets the value of an annotation.
     */
    public function get();
    /**
     * Gets the name of the provider of the annotated class.
     *
     * @return string
     *   The provider of the annotated class.
     */
    public function get_provider();
    /**
     * Sets the name of the provider of the annotated class.
     *
     * @param string $provider
     *   The provider of the annotated class.
     */
    public function set_provider($provider);
    /**
     * Gets the unique ID for this annotated class.
     *
     * @return string
     *   The annotated class ID.
     */
    public function get_id();
    /**
     * Gets the class of the annotated class.
     *
     * @return string
     *   The class name of the annotated class.
     */
    public function get_class();
    /**
     * Sets the class of the annotated class.
     *
     * @param string $class
     *   The class of the annotated class.
     */
    public function set_class($class);
}