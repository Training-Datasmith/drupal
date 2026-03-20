<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Discovery;

/**
 * Ensures that all definitions are run through the attribute process.
 */
class Attribute_Bridge_Decorator implements Discovery_Interface
{
    use Discovery_Trait;
    /**
     * AttributeBridgeDecorator constructor.
     *
     * @param \Drupal\Component\Plugin\Discovery\DiscoveryInterface $decorated
     *   The discovery object that is being decorated.
     * @param string $pluginDefinitionAttributeName
     *   The name of the attribute that contains the plugin definition. The class
     *   corresponding to this name must implement
     *   \Drupal\Component\Plugin\Attribute\AttributeInterface.
     */
    public function __construct(protected readonly Discovery_Interface $decorated, protected readonly string $plugin_definition_attribute_name)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get_definitions()
    {
        $definitions = $this->decorated->get_definitions();
        foreach ($definitions as $id => $definition) {
            // Attribute constructors expect an array of values. If the definition is
            // not an array, it usually means it has been processed already and can be
            // ignored.
            if (is_array($definition)) {
                $class = $definition['class'] ?? null;
                $provider = $definition['provider'] ?? null;
                unset($definition['class'], $definition['provider']);
                /** @var \Drupal\Component\Plugin\Attribute\AttributeInterface $attribute */
                $attribute = new $this->plugin_definition_attribute_name(...$definition);
                if (isset($class)) {
                    $attribute->set_class($class);
                }
                if (isset($provider)) {
                    $attribute->set_provider($provider);
                }
                $definitions[$id] = $attribute->get();
            }
        }
        return $definitions;
    }
    /**
     * Passes through all unknown calls onto the decorated object.
     *
     * @param string $method
     *   The method to call on the decorated plugin discovery.
     * @param array $args
     *   The arguments to send to the method.
     *
     * @return mixed
     *   The method result.
     */
    public function __call(string $method, array $args)
    {
        return $this->decorated->{$method}(...$args);
    }
}