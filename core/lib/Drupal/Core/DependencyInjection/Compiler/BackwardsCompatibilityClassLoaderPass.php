<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Compiler pass to merge moved classes into a single container parameter.
 */
class Backwards_Compatibility_Class_Loader_Pass implements Compiler_Pass_Interface
{
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        $moved_classes = $container->has_parameter('core.moved_classes') ? $container->get_parameter('core.moved_classes') : [];
        $modules = array_keys($container->get_parameter('container.modules'));
        foreach ($modules as $module) {
            $parameter_name = $module . '.moved_classes';
            if ($container->has_parameter($parameter_name)) {
                $module_moved = $container->get_parameter($parameter_name);
                \assert(is_array($module_moved));
                \assert(count($module_moved) === count(array_column($module_moved, 'class')), 'Missing class key for moved classes in ' . $module);
                $moved_classes = $moved_classes + $module_moved;
            }
        }
        if (!empty($moved_classes)) {
            $container->set_parameter('moved_classes', $moved_classes);
        }
    }
}