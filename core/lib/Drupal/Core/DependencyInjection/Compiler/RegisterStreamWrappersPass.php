<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Service_Locator_Tag_Pass;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Adds services tagged 'stream_wrapper' to the stream_wrapper_manager service.
 */
class Register_Stream_Wrappers_Pass implements Compiler_Pass_Interface
{
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('stream_wrapper_manager')) {
            return;
        }
        $stream_wrapper_manager = $container->get_definition('stream_wrapper_manager');
        $services = [];
        foreach ($container->find_tagged_service_ids('stream_wrapper') as $id => $tags) {
            $class = $container->get_definition($id)->get_class();
            // Loop through all the tags for this stream wrapper as we may have
            // multiple schemes.
            foreach ($tags as $attributes) {
                $stream_wrapper_manager->add_method_call('addStreamWrapper', [$id, $class, $attributes['scheme']]);
            }
            $services[$id] = new Reference($id);
        }
        $stream_wrapper_manager->add_argument(Service_Locator_Tag_Pass::register($container, $services));
    }
}