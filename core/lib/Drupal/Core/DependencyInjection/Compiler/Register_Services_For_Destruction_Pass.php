<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Priority_Tagged_Service_Trait;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Adds services to the "kernel.destructable_services" container parameter.
 *
 * Only services tagged with "needs_destruction" are added.
 *
 * @see \Drupal\Core\DestructableInterface
 */
class Register_Services_For_Destruction_Pass implements Compiler_Pass_Interface
{
    use Priority_Tagged_Service_Trait;
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        $service_ids = array_values(array_map(strval(...), $this->find_and_sort_tagged_services('needs_destruction', $container)));
        $container->set_parameter('kernel.destructable_services', $service_ids);
    }
}