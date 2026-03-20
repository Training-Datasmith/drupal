<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Provides a compiler pass which disables the CORS middleware in case disabled.
 *
 * @see core.services.yml
 */
class Cors_Compiler_Pass implements Compiler_Pass_Interface
{
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        $enabled = false;
        if ($cors_config = $container->get_parameter('cors.config')) {
            $enabled = !empty($cors_config['enabled']);
        }
        // Remove the CORS middleware completely in case it was not enabled.
        if (!$enabled) {
            $container->remove_definition('http_middleware.cors');
        }
    }
}