<?php

declare (strict_types=1);
namespace Drupal\Component\Annotation\Plugin\Discovery;

use Drupal\Component\Plugin\Discovery\Discovery_Interface;
use Drupal\Component\Plugin\Discovery\Discovery_Trait;
/**
 * Ensures that all definitions are run through the annotation process.
 */
class Annotation_Bridge_Decorator implements Discovery_Interface
{
    use Discovery_Trait;
    /**
     * ObjectDefinitionDiscoveryDecorator constructor.
     *
     * @param \Drupal\Component\Plugin\Discovery\DiscoveryInterface $decorated
     *   The discovery object that is being decorated.
     * @param string $pluginDefinitionAnnotationName
     *   The name of the annotation that contains the plugin definition. The class
     *   corresponding to this name must implement
     *   \Drupal\Component\Annotation\AnnotationInterface.
     */
    public function __construct(
        protected \Drupal\Component\Plugin\Discovery\Discovery_Interface $decorated,
        /**
         * The name of the annotation that contains the plugin definition.
         */
        protected $plugin_definition_annotation_name
    )
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get_definitions()
    {
        $definitions = $this->decorated->get_definitions();
        foreach ($definitions as $id => $definition) {
            // Annotation constructors expect an array of values. If the definition is
            // not an array, it usually means it has been processed already and can be
            // ignored.
            if (is_array($definition)) {
                $definitions[$id] = (new $this->plugin_definition_annotation_name($definition))->get();
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
        return call_user_func_array([$this->decorated, $method], $args);
    }
}