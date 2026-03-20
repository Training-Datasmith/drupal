<?php

declare (strict_types=1);
namespace Drupal\Component\Proxy_Builder;

/**
 * Generates the string representation of the proxy service.
 */
class Proxy_Builder
{
    /**
     * Generates the used proxy class name from a given class name.
     *
     * @param string $class_name
     *   The class name of the actual service.
     *
     * @return string
     *   The class name of the proxy.
     */
    public static function build_proxy_class_name($class_name): string
    {
        $match = [];
        preg_match('/([a-zA-Z0-9_]+\\\\[a-zA-Z0-9_]+)\\\\(.+)/', $class_name, $match);
        $root_namespace = $match[1];
        $rest_fqcn = $match[2];
        return $root_namespace . '\ProxyClass\\' . $rest_fqcn;
    }
    /**
     * Generates the used proxy namespace from a given class name.
     *
     * @param string $class_name
     *   The class name of the actual service.
     *
     * @return string
     *   The namespace name of the proxy.
     */
    public static function build_proxy_namespace($class_name): string
    {
        $proxy_classname = static::build_proxy_class_name($class_name);
        preg_match('/(.+)\\\\[a-zA-Z0-9]+/', $proxy_classname, $match);
        return $match[1];
    }
    /**
     * Builds a proxy class string.
     *
     * @param string $class_name
     *   The class name of the actual service.
     *
     * @return string
     *   The full string with namespace class and methods.
     */
    public function build($class_name): string
    {
        $reflection = new \ReflectionClass($class_name);
        $proxy_class_name = static::build_proxy_class_name($class_name);
        $proxy_namespace = static::build_proxy_namespace($class_name);
        $proxy_class_shortname = str_replace($proxy_namespace . '\\', '', $proxy_class_name);
        $output = '';
        $class_documentation = <<<'EOS'
        
        namespace {{ namespace }}{
        
            /**
             * Provides a proxy class for \{{ class_name }}.
             *
             * @see \Drupal\Component\ProxyBuilder
             */
        
        EOS;
        $class_start = '    class {{ proxy_class_shortname }}';
        // For cases in which the implemented interface is a child of another
        // interface, getInterfaceNames() also returns the parent. This causes a
        // PHP error.
        // In order to avoid that, check for each interface, whether one of its
        // parents is also in the list and exclude it.
        if ($interfaces = $reflection->get_interfaces()) {
            foreach ($interfaces as $interface) {
                // Exclude all parents from the list of implemented interfaces of the
                // class.
                if ($parent_interfaces = $interface->get_interface_names()) {
                    foreach ($parent_interfaces as $parent_interface) {
                        unset($interfaces[$parent_interface]);
                    }
                }
            }
            $interface_names = [];
            foreach ($interfaces as $interface) {
                $interface_names[] = '\\' . $interface->get_name();
            }
            $class_start .= ' implements ' . implode(', ', $interface_names);
        }
        $output .= $this->build_use_statements();
        // The actual class.
        $properties = <<<'EOS'
        /**
         * The id of the original proxied service.
         *
         * @var string
         */
        protected $drupalProxyOriginalServiceId;
        
        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \{{ class_name }}
         */
        protected $service;
        
        /**
         * The service container.
         *
         * @var \Symfony\Component\DependencyInjection\ContainerInterface
         */
        protected $container;
        
        
        EOS;
        $output .= $properties;
        // Add all the methods.
        $methods = [];
        $methods[] = $this->build_constructor_method();
        $methods[] = $this->build_lazy_load_itself_method();
        // Add all the methods of the proxied service.
        $reflection_methods = $reflection->get_methods();
        foreach ($reflection_methods as $method) {
            if ($method->get_name() === '__construct') {
                continue;
            }
            if ($method->is_public()) {
                $methods[] = $this->build_method($method) . "\n";
            }
        }
        $output .= implode("\n", $methods);
        // Indent the output.
        $output = implode("\n", array_map(function ($value): string {
            if ($value === '') {
                return $value;
            }
            return "        {$value}";
        }, explode("\n", $output)));
        $final_output = $class_documentation . $class_start . "\n    {\n\n" . $output . "\n    }\n\n}\n";
        $final_output = str_replace('{{ class_name }}', $class_name, $final_output);
        $final_output = str_replace('{{ namespace }}', $proxy_namespace ? $proxy_namespace . ' ' : '', $final_output);
        return str_replace('{{ proxy_class_shortname }}', $proxy_class_shortname, $final_output);
    }
    /**
     * Generates the string for the method which loads the actual service.
     *
     * @return string
     *   A string for the lazyLoadItself method.
     */
    protected function build_lazy_load_itself_method(): string
    {
        return <<<'EOS'
        /**
         * Lazy loads the real service from the container.
         *
         * @return object
         *   Returns the constructed real service.
         */
        protected function lazyLoadItself()
        {
            if (!isset($this->service)) {
                $this->service = $this->container->get($this->drupalProxyOriginalServiceId);
            }
        
            return $this->service;
        }
        
        EOS;
    }
    /**
     * Generates the string representation of a single method: signature, body.
     *
     * @param \ReflectionMethod $reflection_method
     *   A reflection method for the method.
     *
     * @return string
     *   The docblock, signature, and body for a method.
     */
    protected function build_method(\ReflectionMethod $reflection_method): string
    {
        $parameters = [];
        foreach ($reflection_method->get_parameters() as $parameter) {
            $parameters[] = $this->build_parameter($parameter);
        }
        $function_name = $reflection_method->get_name();
        $reference = '';
        if ($reflection_method->returns_reference()) {
            $reference = '&';
        }
        $signature_line = <<<'EOS'
        /**
         * {@inheritdoc}
         */
        
        EOS;
        if ($reflection_method->is_static()) {
            $signature_line .= 'public static function ' . $reference . $function_name . '(';
        } else {
            $signature_line .= 'public function ' . $reference . $function_name . '(';
        }
        $signature_line .= implode(', ', $parameters);
        $signature_line .= ')';
        if ($reflection_method->has_return_type()) {
            $signature_line .= ': ';
            $return_type = $reflection_method->get_return_type();
            if ($return_type->allows_null()) {
                $signature_line .= '?';
            }
            if (!$return_type->is_builtin()) {
                // The parameter is a class or interface.
                $signature_line .= '\\';
            }
            $return_type_name = $return_type->get_name();
            if ($return_type_name === 'self') {
                $return_type_name = $reflection_method->get_declaring_class()->get_name();
            }
            $signature_line .= $return_type_name;
        }
        $output = $signature_line . "\n{\n";
        $output .= $this->build_method_body($reflection_method);
        return $output . ("\n" . '}');
    }
    /**
     * Builds a string for a single parameter of a method.
     *
     * @param \ReflectionParameter $parameter
     *   A reflection object of the parameter.
     *
     * @return string
     *   A parameter string.
     */
    protected function build_parameter(\ReflectionParameter $parameter): string
    {
        $parameter_string = '';
        if ($parameter->has_type()) {
            $type = $parameter->get_type();
            if ($type->allows_null()) {
                $parameter_string .= '?';
            }
            if (!$type->is_builtin()) {
                // The parameter is a class or interface.
                $parameter_string .= '\\';
            }
            $type_name = $type->get_name();
            if ($type_name === 'self') {
                $type_name = $parameter->get_declaring_class()->get_name();
            }
            $parameter_string .= $type_name . ' ';
        }
        if ($parameter->is_passed_by_reference()) {
            $parameter_string .= '&';
        }
        $parameter_string .= '$' . $parameter->get_name();
        if ($parameter->is_default_value_available()) {
            $parameter_string .= ' = ';
            $parameter_string .= var_export($parameter->get_default_value(), true);
        }
        return $parameter_string;
    }
    /**
     * Builds the body of a wrapped method.
     *
     * @param \ReflectionMethod $reflection_method
     *   A reflection method for the method.
     *
     * @return string
     *   The body for a method.
     */
    protected function build_method_body(\ReflectionMethod $reflection_method): string
    {
        $output = '';
        $function_name = $reflection_method->get_name();
        if (!$reflection_method->is_static()) {
            if ($reflection_method->get_return_type() && $reflection_method->get_return_type()->get_name() === 'void') {
                $output .= '    $this->lazyLoadItself()->' . $function_name . '(';
            } else {
                $output .= '    return $this->lazyLoadItself()->' . $function_name . '(';
            }
        } else {
            $class_name = $reflection_method->get_declaring_class()->get_name();
            $output .= "    \\{$class_name}::{$function_name}(";
        }
        // Add parameters.
        $parameters = [];
        foreach ($reflection_method->get_parameters() as $parameter) {
            $parameters[] = '$' . $parameter->get_name();
        }
        return $output . (implode(', ', $parameters) . ');');
    }
    /**
     * Builds the constructor used to inject the actual service ID.
     *
     * @return string
     *   The constructor for a class.
     */
    protected function build_constructor_method(): string
    {
        return <<<'EOS'
        /**
         * Constructs a ProxyClass Drupal proxy object.
         *
         * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
         *   The container.
         * @param string $drupal_proxy_original_service_id
         *   The service ID of the original service.
         */
        public function __construct(\Symfony\Component\DependencyInjection\ContainerInterface $container, $drupal_proxy_original_service_id)
        {
            $this->container = $container;
            $this->drupalProxyOriginalServiceId = $drupal_proxy_original_service_id;
        }
        
        EOS;
    }
    /**
     * Build the required use statements of the proxy class.
     *
     * @return string
     *   The use statements.
     */
    protected function build_use_statements(): string
    {
        return '';
    }
}