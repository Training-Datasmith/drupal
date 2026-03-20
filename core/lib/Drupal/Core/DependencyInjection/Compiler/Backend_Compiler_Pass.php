<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Defines a compiler pass to allow automatic override per backend.
 *
 * A module developer has to tag a backend service with "backend_overridable":
 * @code
 * custom_service:
 *   class: ...
 *   tags:
 *     - { name: backend_overridable }
 * @endcode
 *
 * As a site admin you set the 'default_backend' in your services.yml file:
 * @code
 * parameters:
 *   default_backend: sqlite
 * @endcode
 *
 * As a developer for alternative storage engines you register a service with
 * $your_backend.$original_service:
 *
 * @code
 * sqlite.custom_service:
 *   class: ...
 * @endcode
 */
class Backend_Compiler_Pass implements Compiler_Pass_Interface
{
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        if ($container->has_parameter('default_backend')) {
            $default_backend = $container->get_parameter('default_backend');
            // Opt out from the default backend.
            if (!$default_backend) {
                return;
            }
        } else {
            try {
                $driver_backend = $container->get('database')->driver();
                $default_backend = $container->get('database')->database_type();
                $container->set('database', null);
            } catch (\Exception) {
                // If Drupal is not installed or a test doesn't define database there
                // is nothing to override.
                return;
            }
        }
        foreach ($container->find_tagged_service_ids('backend_overridable') as $id => $attributes) {
            // If the service is already an alias it is not the original backend, so
            // we don't want to fallback to other storages any longer.
            if ($container->has_alias($id)) {
                continue;
            }
            if (isset($driver_backend) && ($container->has_definition("{$driver_backend}.{$id}") || $container->has_alias("{$driver_backend}.{$id}"))) {
                $container->set_alias($id, new Alias("{$driver_backend}.{$id}"));
            } elseif (!empty($default_backend) && ($container->has_definition("{$default_backend}.{$id}") || $container->has_alias("{$default_backend}.{$id}"))) {
                $container->set_alias($id, new Alias("{$default_backend}.{$id}"));
            }
        }
    }
}