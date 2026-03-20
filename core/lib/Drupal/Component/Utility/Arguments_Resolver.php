<?php

declare (strict_types=1);
namespace Drupal\Component\Utility;

/**
 * Resolves the arguments to pass to a callable.
 */
class Arguments_Resolver implements Arguments_Resolver_Interface
{
    /**
     * Constructs a new ArgumentsResolver.
     *
     * @param array $scalars
     *   An associative array of parameter names to scalar candidate values.
     * @param object[] $objects
     *   An associative array of parameter names to object candidate values.
     * @param object[] $wildcards
     *   An array object candidates tried on every parameter regardless of its
     *   name.
     */
    public function __construct(protected array $scalars, protected array $objects, protected array $wildcards)
    {
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_arguments(callable $callable): array
    {
        $arguments = [];
        foreach ($this->get_reflector($callable)->get_parameters() as $parameter) {
            $arguments[] = $this->get_argument($parameter);
        }
        return $arguments;
    }
    /**
     * Gets the argument value for a parameter.
     *
     * @param \ReflectionParameter $parameter
     *   The parameter of a callable to get the value for.
     *
     * @return mixed
     *   The value of the requested parameter value.
     *
     * @throws \RuntimeException
     *   Thrown when there is a missing parameter.
     */
    protected function get_argument(\ReflectionParameter $parameter)
    {
        $parameter_type_hint = Reflection::get_parameter_class_name($parameter);
        $parameter_name = $parameter->get_name();
        // If the argument exists and is NULL, return it, regardless of
        // parameter type hint.
        if (!isset($this->objects[$parameter_name]) && array_key_exists($parameter_name, $this->objects)) {
            return null;
        }
        if ($parameter_type_hint) {
            $parameter_type_hint = new \ReflectionClass($parameter_type_hint);
            // If the argument exists and complies with the type hint, return it.
            if (isset($this->objects[$parameter_name]) && is_object($this->objects[$parameter_name]) && $parameter_type_hint->is_instance($this->objects[$parameter_name])) {
                return $this->objects[$parameter_name];
            }
            // Otherwise, resolve wildcard arguments by type matching.
            foreach ($this->wildcards as $wildcard) {
                if ($parameter_type_hint->is_instance($wildcard)) {
                    return $wildcard;
                }
            }
        } elseif (isset($this->scalars[$parameter_name])) {
            return $this->scalars[$parameter_name];
        }
        // If the callable provides a default value, use it.
        if ($parameter->is_default_value_available()) {
            return $parameter->get_default_value();
        }
        // Can't resolve it: call a method that throws an exception or can be
        // overridden to do something else.
        return $this->handle_unresolved_argument($parameter);
    }
    /**
     * Gets a reflector for the access check callable.
     *
     * The access checker may be either a procedural function (in which case the
     * callable is the function name) or a method (in which case the callable is
     * an array of the object and method name).
     *
     * @param callable $callable
     *   The callable (either a function or a method).
     *
     * @return \ReflectionFunctionAbstract
     *   The ReflectionMethod or ReflectionFunction to introspect the callable.
     */
    protected function get_reflector(callable $callable): \ReflectionMethod|\ReflectionFunction
    {
        if (is_array($callable)) {
            return new \ReflectionMethod($callable[0], $callable[1]);
        }
        if (is_string($callable) && str_contains($callable, '::')) {
            return \ReflectionMethod::create_from_method_name($callable);
        }
        return new \ReflectionFunction($callable);
    }
    /**
     * Handles unresolved arguments for getArgument().
     *
     * Subclasses that override this method may return a default value
     * instead of throwing an exception.
     *
     * @throws \RuntimeException
     *   Thrown when there is a missing parameter.
     */
    protected function handle_unresolved_argument(\ReflectionParameter $parameter)
    {
        $class = $parameter->get_declaring_class();
        $function = $parameter->get_declaring_function();
        if ($class && !$function->is_closure()) {
            $function_name = $class->get_name() . '::' . $function->get_name();
        } else {
            $function_name = $function->get_name();
        }
        throw new \RuntimeException(sprintf('Callable "%s" requires a value for the "$%s" argument.', $function_name, $parameter->get_name()));
    }
}