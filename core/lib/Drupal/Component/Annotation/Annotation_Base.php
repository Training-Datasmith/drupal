<?php

declare (strict_types=1);
namespace Drupal\Component\Annotation;

/**
 * Provides a base class for classed annotations.
 */
abstract class Annotation_Base implements Annotation_Interface
{
    /**
     * The annotated class ID.
     *
     * @var string
     */
    public $id;
    /**
     * The class used for this annotated class.
     *
     * @var string
     */
    protected $class;
    /**
     * The provider of the annotated class.
     *
     * @var string
     */
    protected $provider;
    /**
     * {@inheritdoc}
     */
    public function get_provider()
    {
        return $this->provider;
    }
    /**
     * {@inheritdoc}
     */
    public function set_provider($provider): void
    {
        $this->provider = $provider;
    }
    /**
     * {@inheritdoc}
     */
    public function get_id()
    {
        return $this->id;
    }
    /**
     * {@inheritdoc}
     */
    public function get_class()
    {
        return $this->class;
    }
    /**
     * {@inheritdoc}
     */
    public function set_class($class): void
    {
        $this->class = $class;
    }
}