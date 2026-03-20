<?php

declare (strict_types=1);
namespace Drupal\Component\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Argument\Rewindable_Generator;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Exception\Parameter_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Exception\Service_Circular_Reference_Exception;
use Symfony\Component\Dependency_Injection\Exception\Service_Not_Found_Exception;
use Symfony\Contracts\Service\Reset_Interface;
/**
 * Provides a container optimized for Drupal's needs.
 *
 * This container implementation is compatible with the default Symfony
 * dependency injection container and similar to the Symfony ContainerBuilder
 * class, but optimized for speed.
 *
 * It is based on a PHP array container definition dumped as a
 * performance-optimized machine-readable format.
 *
 * The best way to initialize this container is to use a Container Builder,
 * compile it and then retrieve the definition via
 * \Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper::getArray().
 *
 * The retrieved array can be cached safely and then passed to this container
 * via the constructor.
 *
 * As the container is unfrozen by default, a second parameter can be passed to
 * the container to "freeze" the parameter bag.
 *
 * This container is different in behavior from the default Symfony container in
 * the following ways:
 *
 * - It only allows lowercase service and parameter names, though it does only
 *   enforce it via assertions for performance reasons.
 * - The following functions, that are not part of the interface, are explicitly
 *   not supported: getParameterBag(), isFrozen(), compile(),
 *   getAServiceWithAnIdByCamelCase().
 * - The function getServiceIds() was added as it has a use-case in core and
 *   contrib.
 *
 * @ingroup container
 */
class Container implements Container_Interface, Reset_Interface
{
    /**
     * The parameters of the container.
     *
     * @var array
     */
    protected $parameters = [];
    /**
     * The aliases of the container.
     *
     * @var array
     */
    protected $aliases = [];
    /**
     * The service definitions of the container.
     *
     * @var array
     */
    protected $service_definitions = [];
    /**
     * The instantiated services.
     *
     * @var array
     */
    protected $services = [];
    /**
     * The instantiated private services.
     *
     * @var array
     */
    protected $private_services = [];
    /**
     * The currently loading services.
     *
     * @var array
     */
    protected $loading = [];
    /**
     * Whether the container parameters can still be changed.
     *
     * For testing purposes the container needs to be changed.
     *
     * @var bool
     */
    protected $frozen = true;
    /**
     * Constructs a new Container instance.
     *
     * @param array $container_definition
     *   An array containing the following keys:
     *   - aliases: The aliases of the container.
     *   - parameters: The parameters of the container.
     *   - services: The service definitions of the container.
     *   - frozen: Whether the container definition came from a frozen
     *     container builder or not.
     *   - machine_format: Whether this container definition uses the optimized
     *     machine-readable container format.
     */
    public function __construct(array $container_definition = [])
    {
        if (!empty($container_definition) && (!isset($container_definition['machine_format']) || $container_definition['machine_format'] !== true)) {
            throw new InvalidArgumentException('The non-optimized format is not supported by this class. Use an optimized machine-readable format instead, e.g. as produced by \Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper.');
        }
        $this->aliases = $container_definition['aliases'] ?? [];
        $this->parameters = $container_definition['parameters'] ?? [];
        $this->service_definitions = $container_definition['services'] ?? [];
        $this->frozen = $container_definition['frozen'] ?? false;
    }
    /**
     * {@inheritdoc}
     */
    public function get(string $id, int $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE): ?object
    {
        if (isset($this->aliases[$id])) {
            $alias = $id;
            $id = $this->aliases[$id];
        }
        // Re-use shared service instance if it exists.
        if (isset($this->services[$id]) || $invalid_behavior === Container_Interface::NULL_ON_INVALID_REFERENCE && array_key_exists($id, $this->services)) {
            return $this->services[$id];
        }
        if ($id === 'service_container') {
            return $this;
        }
        if (isset($this->loading[$id])) {
            throw new Service_Circular_Reference_Exception($id, array_keys($this->loading));
        }
        $definition = $this->service_definitions[$id] ?? null;
        if (!$definition && $invalid_behavior === Container_Interface::EXCEPTION_ON_INVALID_REFERENCE) {
            if (!$id) {
                throw new Service_Not_Found_Exception('');
            }
            throw new Service_Not_Found_Exception($id, null, null, $this->get_service_alternatives($id));
        }
        // In case something else than ContainerInterface::NULL_ON_INVALID_REFERENCE
        // is used, the actual wanted behavior is to re-try getting the service at a
        // later point.
        if (!$definition) {
            return null;
        }
        // Definition is a keyed array, so [0] is only defined when it is a
        // serialized string.
        if (isset($definition[0])) {
            $definition = unserialize($definition);
        }
        // Now create the service.
        $this->loading[$id] = true;
        try {
            $service = $this->create_service($definition, $id);
        } catch (\Exception $e) {
            unset($this->loading[$id]);
            unset($this->services[$id]);
            if (Container_Interface::EXCEPTION_ON_INVALID_REFERENCE !== $invalid_behavior) {
                return null;
            }
            throw $e;
        }
        unset($this->loading[$id]);
        if (isset($this->parameters['_deprecated_service_list'][$id])) {
            @trigger_error($this->parameters['_deprecated_service_list'][$id], E_USER_DEPRECATED);
        }
        if (isset($alias) && isset($this->parameters['_deprecated_service_list'][$alias])) {
            @trigger_error($this->parameters['_deprecated_service_list'][$alias], E_USER_DEPRECATED);
        }
        return $service;
    }
    /**
     * Resets shared services from the container.
     *
     * The container is not intended to be used again after being reset in a
     * normal workflow. This method is meant as a way to release references for
     * ref-counting. A subsequent call to ContainerInterface::get() will recreate
     * a new instance of the shared service.
     */
    public function reset(): void
    {
        $this->services = [];
    }
    /**
     * Creates a service from a service definition.
     *
     * @param array $definition
     *   The service definition to create a service from.
     * @param string $id
     *   The service identifier, necessary so it can be shared if its public.
     *
     * @return object
     *   The service described by the service definition.
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\RuntimeException
     *   Thrown when the service is a synthetic service.
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     *   Thrown when the configurator callable in $definition['configurator'] is
     *   not actually a callable.
     * @throws \ReflectionException
     *   Thrown when the service class takes more than 10 parameters to construct,
     *   and cannot be instantiated.
     */
    protected function create_service(array $definition, $id)
    {
        if (isset($definition['synthetic']) && $definition['synthetic'] === true) {
            throw new RuntimeException(sprintf('You have requested a synthetic service ("%s"). The service container does not know how to construct this service. The service will need to be set before it is first used.', $id));
        }
        $arguments = [];
        if (isset($definition['arguments'])) {
            $arguments = $definition['arguments'];
            if ($arguments instanceof \stdClass) {
                $arguments = $this->resolve_services_and_parameters($arguments);
            }
        }
        if (isset($definition['file'])) {
            $file = $this->frozen ? $definition['file'] : current($this->resolve_services_and_parameters([$definition['file']]));
            require_once $file;
        }
        if (isset($definition['factory'])) {
            $factory = $definition['factory'];
            if (is_array($factory)) {
                $factory = $this->resolve_services_and_parameters([$factory[0], $factory[1]]);
            } elseif (!is_string($factory)) {
                throw new RuntimeException(sprintf('Cannot create service "%s" because of invalid factory', $id));
            }
            $service = call_user_func_array($factory, $arguments);
        } else {
            $class = $this->frozen ? $definition['class'] : current($this->resolve_services_and_parameters([$definition['class']]));
            $service = new $class(...$arguments);
        }
        if (!isset($definition['shared']) || $definition['shared'] !== false) {
            $this->services[$id] = $service;
        }
        if (isset($definition['calls'])) {
            foreach ($definition['calls'] as $call) {
                $method = $call[0];
                $arguments = [];
                if (!empty($call[1])) {
                    $arguments = $call[1];
                    if ($arguments instanceof \stdClass) {
                        $arguments = $this->resolve_services_and_parameters($arguments);
                    }
                }
                call_user_func_array([$service, $method], $arguments);
            }
        }
        if (isset($definition['properties'])) {
            if ($definition['properties'] instanceof \stdClass) {
                $definition['properties'] = $this->resolve_services_and_parameters($definition['properties']);
            }
            foreach ($definition['properties'] as $key => $value) {
                $service->{$key} = $value;
            }
        }
        if (isset($definition['configurator'])) {
            $callable = $definition['configurator'];
            if (is_array($callable)) {
                $callable = $this->resolve_services_and_parameters($callable);
            }
            if (!is_callable($callable)) {
                throw new InvalidArgumentException(sprintf('The configurator for class "%s" is not a callable.', $service::class));
            }
            call_user_func($callable, $service);
        }
        return $service;
    }
    /**
     * {@inheritdoc}
     */
    public function set(string $id, ?object $service): void
    {
        $this->services[$id] = $service;
    }
    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return isset($this->aliases[$id]) || isset($this->services[$id]) || isset($this->service_definitions[$id]) || $id === 'service_container';
    }
    /**
     * {@inheritdoc}
     */
    public function get_parameter(string $name): array|bool|string|int|float|\Unit_Enum|null
    {
        if (!\array_key_exists($name, $this->parameters)) {
            throw new Parameter_Not_Found_Exception($name, null, null, null, $this->get_parameter_alternatives($name));
        }
        return $this->parameters[$name];
    }
    /**
     * {@inheritdoc}
     */
    public function has_parameter(string $name): bool
    {
        return \array_key_exists($name, $this->parameters);
    }
    /**
     * {@inheritdoc}
     */
    public function set_parameter(string $name, array|bool|string|int|float|\Unit_Enum|null $value): void
    {
        if ($this->frozen) {
            throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
        }
        $this->parameters[$name] = $value;
    }
    /**
     * {@inheritdoc}
     */
    public function initialized(string $id): bool
    {
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }
        return \array_key_exists($id, $this->services);
    }
    /**
     * Resolves arguments that represent services or variables to the real values.
     *
     * @param array|object $arguments
     *   The arguments to resolve.
     *
     * @return array
     *   The resolved arguments.
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\RuntimeException
     *   If a parameter/service could not be resolved.
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     *   If an unknown type is met while resolving parameters and services.
     */
    protected function resolve_services_and_parameters(array $arguments)
    {
        // Check if this collection needs to be resolved.
        if ($arguments instanceof \stdClass) {
            if ($arguments->type !== 'collection') {
                throw new InvalidArgumentException(sprintf('Undefined type "%s" while resolving parameters and services.', $arguments->type));
            }
            $arguments = $arguments->value;
        }
        // Process the arguments.
        foreach ($arguments as $key => $argument) {
            // For this machine-optimized format, only \stdClass arguments are
            // processed and resolved. All other values are kept as is.
            if ($argument instanceof \stdClass) {
                $type = $argument->type;
                // Check for parameter.
                if ($type == 'parameter') {
                    $name = $argument->name;
                    if (!isset($this->parameters[$name])) {
                        $arguments[$key] = $this->get_parameter($name);
                        // This can never be reached as getParameter() throws an Exception,
                        // because we already checked that the parameter is not set above.
                    }
                    // Update argument.
                    $argument = $arguments[$key] = $this->parameters[$name];
                    // In case there is not a machine readable value (e.g. a service)
                    // behind this resolved parameter, continue.
                    if (!$argument instanceof \stdClass) {
                        continue;
                    }
                    // Fall through.
                    $type = $argument->type;
                }
                // Create a service.
                if ($type == 'service') {
                    $id = $argument->id;
                    // Does the service already exist?
                    if (isset($this->aliases[$id])) {
                        $id = $this->aliases[$id];
                    }
                    if (isset($this->services[$id])) {
                        $arguments[$key] = $this->services[$id];
                        continue;
                    }
                    // Return the service.
                    $arguments[$key] = $this->get($id, $argument->invalid_behavior);
                    continue;
                }
                // Create private service.
                if ($type == 'private_service') {
                    $id = $argument->id;
                    // Does the private service already exist.
                    if (isset($this->private_services[$id])) {
                        $arguments[$key] = $this->private_services[$id];
                        continue;
                    }
                    // Create the private service.
                    $arguments[$key] = $this->create_service($argument->value, $id);
                    if ($argument->shared) {
                        $this->private_services[$id] = $arguments[$key];
                    }
                    continue;
                }
                if ($type == 'service_closure') {
                    $arguments[$key] = fn() => $this->get($argument->id, $argument->invalid_behavior);
                    continue;
                }
                if ($type == 'iterator') {
                    $services = $argument->value;
                    $arguments[$key] = new Rewindable_Generator(function () use ($services) {
                        foreach ($services as $key => $service) {
                            yield $key => $this->resolve_services_and_parameters([$service])[0];
                        }
                    }, count($services));
                    continue;
                }
                // Check for collection.
                if ($type == 'collection') {
                    $arguments[$key] = $this->resolve_services_and_parameters($argument->value);
                    continue;
                }
                // Create a service.
                if ($type == 'raw') {
                    $arguments[$key] = $argument->value;
                    continue;
                }
                if ($type !== null) {
                    throw new InvalidArgumentException(sprintf('Undefined type "%s" while resolving parameters and services.', $type));
                }
            }
        }
        return $arguments;
    }
    /**
     * Provides alternatives for a given array and key.
     *
     * @param string $search_key
     *   The search key to get alternatives for.
     * @param array $keys
     *   The search space to search for alternatives in.
     *
     * @return string[]
     *   An array of strings with suitable alternatives.
     */
    protected function get_alternatives($search_key, array $keys): array
    {
        $alternatives = [];
        foreach ($keys as $key) {
            $lev = levenshtein($search_key, $key);
            if ($lev <= strlen($search_key) / 3 || str_contains((string) $key, $search_key)) {
                $alternatives[] = $key;
            }
        }
        return $alternatives;
    }
    /**
     * Provides alternatives in case a service was not found.
     *
     * @param string $id
     *   The service to get alternatives for.
     *
     * @return string[]
     *   An array of strings with suitable alternatives.
     */
    protected function get_service_alternatives($id)
    {
        $all_service_keys = array_unique(array_merge(array_keys($this->services), array_keys($this->service_definitions)));
        return $this->get_alternatives($id, $all_service_keys);
    }
    /**
     * Provides alternatives in case a parameter was not found.
     *
     * @param string $name
     *   The parameter to get alternatives for.
     *
     * @return string[]
     *   An array of strings with suitable alternatives.
     */
    protected function get_parameter_alternatives($name)
    {
        return $this->get_alternatives($name, array_keys($this->parameters));
    }
    /**
     * {@inheritdoc}
     */
    public function get_service_ids(): array
    {
        return array_merge(['service_container'], array_keys($this->service_definitions + $this->services));
    }
    /**
     * Ensure that cloning doesn't work.
     */
    private function __clone()
    {
    }
}