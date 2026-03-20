<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Drupal\Component\Utility\Crypt;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Adds the twig_extension_hash parameter to the container.
 *
 * Parameter twig_extension_hash is a crc32 hash of all extensions for Twig
 * template invalidation.
 */
class Twig_Extension_Pass implements Compiler_Pass_Interface
{
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        $twig_extension_hash = '';
        foreach (array_keys($container->find_tagged_service_ids('twig.extension')) as $service_id) {
            $class_name = $container->get_definition($service_id)->get_class();
            $reflection = new \ReflectionClass($class_name);
            // We use the class names as hash in order to invalidate on new extensions
            // and crc32 for every time we change an existing file.
            $twig_extension_hash .= $class_name . hash_file('crc32', $reflection->get_file_name());
        }
        $container->set_parameter('twig_extension_hash', Crypt::hash_base64($twig_extension_hash));
    }
}