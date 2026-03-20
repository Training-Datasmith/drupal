<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Drupal\Core\Stack_Middleware\Stacked_Http_Kernel;
use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Compiler\Abstract_Recursive_Pass;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Priority_Tagged_Service_Trait;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Provides a compiler pass for stacked HTTP kernels.
 *
 * Builds the HTTP kernel by collecting all services tagged 'http_middleware'
 * and assembling them into a StackedHttpKernel. The middleware with the highest
 * priority ends up as the outermost while the lowest priority middleware wraps
 * the actual HTTP kernel defined by the http_kernel.basic service.
 *
 * A HTTP middleware may act on a request before and/or after it is delegated to
 * the next inner layer. The inner layer is injected into the middleware in the
 * first constructor argument. The following type hints are supported for the
 * argument: Either Symfony\Component\HttpKernel\HttpKernelInterface or
 * \Closure or an union of both to retain backward compatibility. If the
 * middleware type hint contains a \Closure, the inner layer is injected as a
 * service closure.
 *
 * In general middlewares should not have heavy dependencies. This is especially
 * important for high-priority services which need to run before the internal
 * page cache.
 *
 * An example of a high priority middleware.
 * @code
 * http_middleware.reverse_proxy:
 *   class: Drupal\Core\StackMiddleware\ReverseProxyMiddleware
 *   arguments: ['@settings']
 *   tags:
 *     - { name: http_middleware, priority: 300 }
 * @endcode
 *
 * @see \Drupal\Core\StackMiddleware\StackedHttpKernel
 */
class Stacked_Kernel_Pass extends Abstract_Recursive_Pass implements Compiler_Pass_Interface
{
    use Priority_Tagged_Service_Trait;
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('http_kernel')) {
            return;
        }
        $stacked_kernel = $container->get_definition('http_kernel');
        // Return now if this is not a stacked kernel.
        if ($stacked_kernel->get_class() !== Stacked_Http_Kernel::class) {
            return;
        }
        $decorated_id = 'http_kernel.basic';
        $middlewares_param = [new Reference($decorated_id)];
        foreach (array_reverse($this->find_and_sort_tagged_services('http_middleware', $container)) as $ref) {
            // Prepend a reference to the middlewares container parameter.
            array_unshift($middlewares_param, $ref);
            // Setup an alias on the outer middleware pointing to the inner one.
            $decorator_id = (string) $ref;
            $container->set_alias($decorator_id . '.http_middleware_inner', $decorated_id);
            $decorated_id = $decorator_id;
        }
        $arguments = [new Reference($decorated_id), new Iterator_Argument($middlewares_param)];
        $stacked_kernel->set_arguments($arguments);
        parent::process($container);
    }
    /**
     * {@inheritdoc}
     */
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        $value = parent::process_value($value, $is_root);
        if (!$value instanceof Definition || !$value->has_tag('http_middleware')) {
            return $value;
        }
        $constructor = $this->get_constructor($value, true);
        $params = $constructor->get_parameters();
        $inner_type = $params[0]->get_type();
        $inner_param_types = $inner_type instanceof \ReflectionUnionType || $inner_type instanceof \ReflectionIntersectionType ? $inner_type->get_types() : [$inner_type];
        $param_type_names = array_map(fn($param): string => (string) $param, $inner_param_types);
        $inner = new Reference($this->current_id . '.http_middleware_inner');
        if (in_array(\Closure::class, $param_type_names, true)) {
            $inner = new Service_Closure_Argument($inner);
        }
        $arguments = $value->get_arguments();
        array_unshift($arguments, $inner);
        $value->set_arguments($arguments);
        return $value;
    }
}