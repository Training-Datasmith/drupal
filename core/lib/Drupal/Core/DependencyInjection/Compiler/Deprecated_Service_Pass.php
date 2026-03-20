<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Sets the _deprecated_service_list parameter.
 *
 * @see \Drupal\Component\DependencyInjection\Container::get()
 */
class Deprecated_Service_Pass implements Compiler_Pass_Interface
{
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        $deprecated_services = [];
        foreach ($container->get_definitions() as $service_id => $definition) {
            if ($definition->is_deprecated()) {
                $deprecated_services[$service_id] = $definition->get_deprecation($service_id)['message'];
            }
        }
        foreach ($container->get_aliases() as $service_id => $definition) {
            if ($definition->is_deprecated()) {
                $deprecated_services[$service_id] = $definition->get_deprecation($service_id)['message'];
            }
        }
        $container->set_parameter('_deprecated_service_list', $deprecated_services);
    }
}