<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Drupal\Component\Proxy_Builder\Proxy_Builder;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Replaces all services with a lazy flag.
 *
 * @see lazy_services
 */
class Proxy_Services_Pass implements Compiler_Pass_Interface
{
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        foreach ($container->get_definitions() as $service_id => $definition) {
            if ($definition->is_lazy()) {
                $proxy_class = Proxy_Builder::build_proxy_class_name($definition->get_class());
                if (class_exists($proxy_class)) {
                    // Copy the existing definition to a new entry.
                    $definition->set_lazy(false);
                    // Ensure that the service is accessible.
                    $definition->set_public(true);
                    $new_service_id = 'drupal.proxy_original_service.' . $service_id;
                    $container->set_definition($new_service_id, $definition);
                    $container->register($service_id, $proxy_class)->set_arguments([new Reference('service_container'), $new_service_id]);
                } else {
                    $class_name = $definition->get_class();
                    // Find the root namespace.
                    $match = [];
                    preg_match('/([a-zA-Z0-9_]+\\\\[a-zA-Z0-9_]+)\\\\(.+)/', (string) $class_name, $match);
                    $root_namespace = $match[1];
                    // Find the root namespace path.
                    $root_namespace_dir = '[namespace_root_path]';
                    $namespaces = $container->get_parameter('container.namespaces');
                    // Hardcode Drupal Core, because it is not registered.
                    $namespaces['Drupal\Core'] = 'core/lib/Drupal/Core';
                    if (isset($namespaces[$root_namespace])) {
                        $root_namespace_dir = $namespaces[$root_namespace];
                    }
                    $message = <<<EOF
                    
                    Missing proxy class '{$proxy_class}' for lazy service '{$service_id}'.
                    Use the following command to generate the proxy class:
                      php core/scripts/generate-proxy-class.php '{$class_name}' "{$root_namespace_dir}"
                    
                    
                    EOF;
                    trigger_error($message, E_USER_WARNING);
                }
            }
        }
    }
}