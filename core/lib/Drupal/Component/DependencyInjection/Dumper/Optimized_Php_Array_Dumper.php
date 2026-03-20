<?php

declare (strict_types=1);
namespace Drupal\Component\Dependency_Injection\Dumper;

use Drupal\Component\Utility\Crypt;
use Symfony\Component\Dependency_Injection\Argument\Iterator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Dumper\Dumper;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Parameter;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Expression_Language\Expression;
/**
 * OptimizedPhpArrayDumper dumps a service container as a serialized PHP array.
 *
 * The format of this dumper is very similar to the internal structure of the
 * ContainerBuilder, but based on PHP arrays and \stdClass objects instead of
 * rich value objects for performance reasons.
 *
 * By removing the abstraction and optimizing some cases like deep collections,
 * fewer classes need to be loaded, fewer function calls need to be executed and
 * fewer run time checks need to be made.
 *
 * In addition to that, this container dumper treats private services as
 * strictly private with their own private services storage, whereas in the
 * Symfony service container builder and PHP dumper, shared private services can
 * still be retrieved via get() from the container.
 *
 * It is machine-optimized, for a human-readable version based on this one see
 * \Drupal\Component\DependencyInjection\Dumper\PhpArrayDumper.
 *
 * @see \Drupal\Component\DependencyInjection\Container
 */
class Optimized_Php_Array_Dumper extends Dumper
{
    /**
     * Whether to serialize service definitions or not.
     *
     * Service definitions are serialized by default to avoid having to
     * unserialize the whole container on loading time, which improves early
     * bootstrap performance for e.g. the page cache.
     *
     * @var bool
     */
    protected $serialize = true;
    /**
     * A list of container aliases.
     *
     * @var array
     */
    protected $aliases;
    /**
     * {@inheritdoc}
     */
    public function dump(array $options = []): string|array
    {
        return serialize($this->get_array());
    }
    /**
     * Gets the service container definition as a PHP array.
     *
     * @return array
     *   A PHP array representation of the service container.
     */
    public function get_array()
    {
        $definition = [];
        // Warm aliases first.
        $this->aliases = $this->get_aliases();
        $definition['aliases'] = $this->aliases;
        $definition['parameters'] = $this->get_parameters();
        $definition['services'] = $this->get_service_definitions();
        $definition['frozen'] = $this->container->is_compiled();
        $definition['machine_format'] = $this->supports_machine_format();
        return $definition;
    }
    /**
     * Gets the aliases as a PHP array.
     *
     * @return array
     *   The aliases.
     */
    protected function get_aliases()
    {
        $alias_definitions = [];
        $aliases = $this->container->get_aliases();
        foreach ($aliases as $alias => $id) {
            $id = (string) $id;
            while (isset($aliases[$id])) {
                $id = (string) $aliases[$id];
            }
            $alias_definitions[$alias] = $id;
        }
        return $alias_definitions;
    }
    /**
     * Gets parameters of the container as a PHP array.
     *
     * @return array
     *   The escaped and prepared parameters of the container.
     */
    protected function get_parameters()
    {
        if (!$this->container->get_parameter_bag()->all()) {
            return [];
        }
        $parameters = $this->container->get_parameter_bag()->all();
        $is_compiled = $this->container->is_compiled();
        return $this->prepare_parameters($parameters, $is_compiled);
    }
    /**
     * Gets services of the container as a PHP array.
     *
     * @return array
     *   The service definitions.
     */
    protected function get_service_definitions()
    {
        if (!$this->container->get_definitions()) {
            return [];
        }
        $services = [];
        foreach ($this->container->get_definitions() as $id => $definition) {
            // Only store public service definitions, references to shared private
            // services are handled in ::getReferenceCall().
            if ($definition->is_public()) {
                $service_definition = $this->get_service_definition($definition);
                $services[$id] = $this->serialize ? serialize($service_definition) : $service_definition;
            }
        }
        return $services;
    }
    /**
     * Prepares parameters for the PHP array dumping.
     *
     * @param array $parameters
     *   An array of parameters.
     * @param bool $escape
     *   Whether keys with '%' should be escaped or not.
     *
     * @return array
     *   An array of prepared parameters.
     */
    protected function prepare_parameters(array $parameters, $escape = true)
    {
        $filtered = [];
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $value = $this->prepare_parameters($value, $escape);
            }
            $filtered[$key] = $value;
        }
        return $escape ? $this->escape($filtered) : $filtered;
    }
    /**
     * Escapes parameters.
     *
     * @param array $parameters
     *   The parameters to escape for '%' characters.
     *
     * @return array
     *   The escaped parameters.
     */
    protected function escape(array $parameters)
    {
        $args = [];
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $args[$key] = $this->escape($value);
            } elseif (is_string($value)) {
                $args[$key] = str_replace('%', '%%', $value);
            } else {
                $args[$key] = $value;
            }
        }
        return $args;
    }
    /**
     * Gets a service definition as PHP array.
     *
     * @param \Symfony\Component\DependencyInjection\Definition $definition
     *   The definition to process.
     *
     * @return array
     *   The service definition as PHP array.
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     *   Thrown when the definition is marked as decorated, or with an explicit
     *   scope different from SCOPE_CONTAINER and SCOPE_PROTOTYPE.
     */
    protected function get_service_definition(Definition $definition)
    {
        $service = [];
        if ($definition->get_class()) {
            $service['class'] = $definition->get_class();
        }
        if (!$definition->is_public()) {
            $service['public'] = false;
        }
        if ($definition->get_file()) {
            $service['file'] = $definition->get_file();
        }
        if ($definition->is_synthetic()) {
            $service['synthetic'] = true;
        }
        if ($definition->is_lazy()) {
            $service['lazy'] = true;
        }
        if ($definition->get_arguments()) {
            $arguments = $definition->get_arguments();
            $service['arguments'] = $this->dump_collection($arguments);
            $service['arguments_count'] = count($arguments);
        } else {
            $service['arguments_count'] = 0;
        }
        if ($definition->get_properties()) {
            $service['properties'] = $this->dump_collection($definition->get_properties());
        }
        if ($definition->get_method_calls()) {
            $service['calls'] = $this->dump_method_calls($definition->get_method_calls());
        }
        // By default services are shared, so just provide the flag, when needed.
        if ($definition->is_shared() === false) {
            $service['shared'] = $definition->is_shared();
        }
        if ($definition->get_decorated_service() !== null) {
            throw new InvalidArgumentException("The 'decorated' definition is not supported by the Drupal run-time container. The Container Builder should have resolved that during the DecoratorServicePass compiler pass.");
        }
        if ($callable = $definition->get_factory()) {
            $service['factory'] = $this->dump_callable($callable);
        }
        if ($callable = $definition->get_configurator()) {
            $service['configurator'] = $this->dump_callable($callable);
        }
        return $service;
    }
    /**
     * Dumps method calls to a PHP array.
     *
     * @param array $calls
     *   An array of method calls.
     *
     * @return array
     *   The PHP array representation of the method calls.
     */
    protected function dump_method_calls(array $calls)
    {
        $code = [];
        foreach ($calls as $key => $call) {
            $method = $call[0];
            $arguments = [];
            if (!empty($call[1])) {
                $arguments = $this->dump_collection($call[1]);
            }
            $code[$key] = [$method, $arguments];
        }
        return $code;
    }
    /**
     * Dumps a collection to a PHP array.
     *
     * @param mixed $collection
     *   A collection to process.
     * @param bool &$resolve
     *   Used for passing the information to the caller whether the given
     *   collection needed to be resolved or not. This is used for optimizing
     *   deep arrays that don't need to be traversed.
     *
     * @return object|array
     *   The collection in a suitable format.
     */
    protected function dump_collection($collection, &$resolve = false)
    {
        $code = [];
        foreach ($collection as $key => $value) {
            if (is_array($value)) {
                $resolve_collection = false;
                $code[$key] = $this->dump_collection($value, $resolve_collection);
                if ($resolve_collection) {
                    $resolve = true;
                }
            } else {
                $code[$key] = $this->dump_value($value);
                if (is_object($code[$key])) {
                    $resolve = true;
                }
            }
        }
        if (!$resolve) {
            return $collection;
        }
        return (object) ['type' => 'collection', 'value' => $code];
    }
    /**
     * Dumps callable to a PHP array.
     *
     * @param array|callable $callable
     *   The callable to process.
     *
     * @return callable
     *   The processed callable.
     */
    protected function dump_callable($callable)
    {
        if (is_array($callable)) {
            $callable[0] = $this->dump_value($callable[0]);
            $callable = [$callable[0], $callable[1]];
        }
        return $callable;
    }
    /**
     * Gets a private service definition in a suitable format.
     *
     * @param string|null $id
     *   The ID of the service to get a private definition for.
     * @param \Symfony\Component\DependencyInjection\Definition $definition
     *   The definition to process.
     * @param bool $shared
     *   (optional) Whether the service will be shared with others.
     *   By default this parameter is FALSE.
     *
     * @return object
     *   A very lightweight private service value object.
     */
    protected function get_private_service_call($id, Definition $definition, $shared = false)
    {
        $service_definition = $this->get_service_definition($definition);
        if (!$id) {
            $hash = Crypt::hash_base64(serialize($service_definition));
            $id = 'private__' . $hash;
        }
        return (object) ['type' => 'private_service', 'id' => $id, 'value' => $service_definition, 'shared' => $shared];
    }
    /**
     * Dumps the value to PHP array format.
     *
     * @param mixed $value
     *   The value to dump.
     *
     * @return mixed
     *   The dumped value in a suitable format.
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\RuntimeException
     *   When trying to dump object or resource.
     */
    protected function dump_value($value)
    {
        if (is_array($value)) {
            $code = [];
            foreach ($value as $k => $v) {
                $code[$k] = $this->dump_value($v);
            }
            return $code;
        }
        if ($value instanceof Reference) {
            return $this->get_reference_call((string) $value, $value);
        }
        if ($value instanceof Definition) {
            return $this->get_private_service_call(null, $value);
        }
        if ($value instanceof Parameter) {
            return $this->get_parameter_call((string) $value);
        }
        if (is_string($value) && str_contains($value, '%')) {
            if (preg_match('/^%([^%]+)%$/', $value, $matches)) {
                return $this->get_parameter_call($matches[1]);
            }
            $replace_parameters = fn($matches) => $this->get_parameter_call($matches[2]);
            // We cannot directly return the string value because it would
            // potentially not always be resolved in the dumpCollection() method.
            return (object) ['type' => 'raw', 'value' => str_replace('%%', '%', preg_replace_callback('/(?<!%)(%)([^%]+)\1/', $replace_parameters, $value))];
        }
        if ($value instanceof Expression) {
            throw new RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
        }
        if ($value instanceof Service_Closure_Argument) {
            $reference = $value->get_values();
            /** @var \Symfony\Component\DependencyInjection\Reference $reference */
            $reference = reset($reference);
            return $this->get_service_closure_call((string) $reference, $reference->get_invalid_behavior());
        }
        if ($value instanceof Iterator_Argument) {
            return $this->getIterator($value);
        }
        if (is_object($value)) {
            throw new RuntimeException('Unable to dump a service container if a parameter is an object.');
        }
        if (is_resource($value)) {
            throw new RuntimeException('Unable to dump a service container if a parameter is a resource.');
        }
        return $value;
    }
    /**
     * Gets a service reference for a reference in a suitable PHP array format.
     *
     * The main difference is that this function treats references to private
     * services differently and returns a private service reference instead of
     * a normal reference.
     *
     * @param string $id
     *   The ID of the service to get a reference for.
     * @param \Symfony\Component\DependencyInjection\Reference|null $reference
     *   (optional) The reference object to process; needed to get the invalid
     *   behavior value.
     *
     * @return string|object
     *   A suitable representation of the service reference.
     */
    protected function get_reference_call($id, ?Reference $reference = null)
    {
        $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE;
        if ($reference !== null) {
            $invalid_behavior = $reference->get_invalid_behavior();
        }
        // Private shared service.
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }
        $definition = $this->container->get_definition($id);
        if (!$definition->is_public()) {
            // The ContainerBuilder does not share a private service, but this means a
            // new service is instantiated every time. Use a private shared service to
            // circumvent the problem.
            return $this->get_private_service_call($id, $definition, true);
        }
        return $this->get_service_call($id, $invalid_behavior);
    }
    /**
     * Gets a service reference for an ID in a suitable PHP array format.
     *
     * @param string $id
     *   The ID of the service to get a reference for.
     * @param int $invalid_behavior
     *   (optional) The invalid behavior of the service.
     *
     * @return string|object
     *   A suitable representation of the service reference.
     */
    protected function get_service_call($id, $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE)
    {
        return (object) ['type' => 'service', 'id' => $id, 'invalidBehavior' => $invalid_behavior];
    }
    /**
     * Gets a parameter reference in a suitable PHP array format.
     *
     * @param string $name
     *   The name of the parameter to get a reference for.
     *
     * @return string|object
     *   A suitable representation of the parameter reference.
     */
    protected function get_parameter_call($name)
    {
        return (object) ['type' => 'parameter', 'name' => $name];
    }
    /**
     * Whether this supports the machine-optimized format or not.
     *
     * @return bool
     *   TRUE if this supports machine-optimized format, FALSE otherwise.
     */
    protected function supports_machine_format()
    {
        return true;
    }
    /**
     * Gets a service closure reference in a suitable PHP array format.
     *
     * @param string $id
     *   The ID of the service to get a reference for.
     * @param int $invalid_behavior
     *   (optional) The invalid behavior of the service.
     *
     * @return string|object
     *   A suitable representation of the service closure reference.
     */
    protected function get_service_closure_call(string $id, int $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE)
    {
        return (object) ['type' => 'service_closure', 'id' => $id, 'invalidBehavior' => $invalid_behavior];
    }
    /**
     * Gets a service iterator in a suitable PHP array format.
     *
     * @param \Symfony\Component\DependencyInjection\Argument\IteratorArgument $iterator
     *   The iterator.
     *
     * @return object
     *   The PHP array representation of the iterator.
     */
    protected function getIterator(Iterator_Argument $iterator)
    {
        return (object) ['type' => 'iterator', 'value' => array_map($this->dump_value(...), $iterator->get_values())];
    }
}