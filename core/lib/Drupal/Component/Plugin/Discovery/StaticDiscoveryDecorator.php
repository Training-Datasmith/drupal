<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Discovery;

/**
 * A decorator that allows manual registration of undiscoverable definitions.
 */
class Static_Discovery_Decorator extends Static_Discovery
{
    /**
     * A callback or closure used for registering additional definitions.
     *
     * @var callable
     */
    protected $register_definitions;
    /**
     * Constructs StaticDiscoveryDecorator object.
     *
     * @param \Drupal\Component\Plugin\Discovery\DiscoveryInterface $decorated
     *   The discovery object that is being decorated.
     * @param callable|null $registerDefinitions
     *   (optional) A callback or closure used for registering additional
     *   definitions.
     */
    public function __construct(protected \Drupal\Component\Plugin\Discovery\Discovery_Interface $decorated, $register_definitions = null)
    {
        $this->register_definitions = $register_definitions;
    }
    /**
     * {@inheritdoc}
     */
    public function get_definition($base_plugin_id, $exception_on_invalid = true)
    {
        if (isset($this->register_definitions)) {
            call_user_func($this->register_definitions);
        }
        $this->definitions += $this->decorated->get_definitions();
        return parent::get_definition($base_plugin_id, $exception_on_invalid);
    }
    /**
     * {@inheritdoc}
     */
    public function get_definitions()
    {
        if (isset($this->register_definitions)) {
            call_user_func($this->register_definitions);
        }
        $this->definitions += $this->decorated->get_definitions();
        return parent::get_definitions();
    }
    /**
     * Passes through all unknown calls onto the decorated object.
     */
    public function __call(string $method, array $args)
    {
        return call_user_func_array([$this->decorated, $method], $args);
    }
}