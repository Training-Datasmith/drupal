<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Drupal\Core\Access\Access_Check_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Service_Locator_Tag_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Adds services tagged 'access_check' to the access_manager service.
 */
class Register_Access_Checks_Pass implements Compiler_Pass_Interface
{
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('access_manager')) {
            return;
        }
        $services = [];
        $dynamic_access_check_services = [];
        // Add services tagged 'access_check' to the access_manager service.
        $access_manager = $container->get_definition('access_manager.check_provider');
        foreach ($container->find_tagged_service_ids('access_check') as $id => $attributes) {
            $applies = [];
            $method = 'access';
            $needs_incoming_request = false;
            foreach ($attributes as $attribute) {
                if (isset($attribute['applies_to'])) {
                    $applies[] = $attribute['applies_to'];
                }
                if (isset($attribute['method'])) {
                    $method = $attribute['method'];
                }
                if (!empty($attribute['needs_incoming_request'])) {
                    $needs_incoming_request = true;
                }
            }
            $access_manager->add_method_call('addCheckService', [$id, $method, $applies, $needs_incoming_request]);
            // Collect dynamic access checker services.
            $class = $container->get_definition($id)->get_class();
            if (in_array(Access_Check_Interface::class, class_implements($class), true)) {
                $dynamic_access_check_services[] = $id;
            }
            $services[$id] = new Reference($id);
        }
        $access_manager->add_argument(Service_Locator_Tag_Pass::register($container, $services));
        $container->set_parameter('dynamic_access_check_services', $dynamic_access_check_services);
    }
}