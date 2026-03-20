<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Definition;

/**
 * Provides object-based plugin definitions.
 */
#[\Allow_Dynamic_Properties]
class Plugin_Definition implements Plugin_Definition_Interface
{
    /**
     * The plugin ID.
     *
     * @var string
     */
    protected $id;
    /**
     * A fully qualified class name.
     *
     * @var string
     */
    protected $class;
    /**
     * The plugin provider.
     *
     * @var string
     */
    protected $provider;
    /**
     * {@inheritdoc}
     */
    public function id()
    {
        return $this->id;
    }
    /**
     * {@inheritdoc}
     */
    public function set_class($class): static
    {
        $this->class = $class;
        return $this;
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
    public function get_provider()
    {
        return $this->provider;
    }
}