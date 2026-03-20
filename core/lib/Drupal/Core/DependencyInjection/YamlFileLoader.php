<?php

declare (strict_types=1);
// phpcs:ignoreFile Portions of this file are a direct copy of
// \Symfony\Component\DependencyInjection\Loader\YamlFileLoader.
namespace Drupal\Core\Dependency_Injection;

use Drupal\Component\File_Cache\File_Cache_Factory;
use Drupal\Component\Serialization\Exception\Invalid_Data_Type_Exception;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Tagged_Iterator_Argument;
use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Yaml\Tag\Tagged_Value;
/**
 * YamlFileLoader loads YAML files service definitions.
 *
 * Drupal does not use Symfony's Config component, and Symfony's dependency on
 * it cannot be removed easily. Therefore, this is a partial but mostly literal
 * copy of upstream, which does not depend on the Config component.
 *
 * @see \Symfony\Component\DependencyInjection\Loader\YamlFileLoader
 * @see https://github.com/symfony/symfony/pull/10920
 *
 * NOTE: 98% of this code is a literal copy of Symfony's YamlFileLoader.
 *
 * This file does NOT follow Drupal coding standards, so as to simplify future
 * synchronizations.
 */
class Yaml_File_Loader
{
    private const DEFAULTS_KEYWORDS = ['public' => 'public', 'tags' => 'tags', 'autowire' => 'autowire', 'autoconfigure' => 'autoconfigure'];
    /**
     * File cache object.
     *
     * @var \Drupal\Component\FileCache\FileCacheInterface
     */
    protected $file_cache;
    public function __construct(protected \Drupal\Core\Dependency_Injection\Container_Builder $container)
    {
        $this->file_cache = File_Cache_Factory::get('container_yaml_loader');
    }
    /**
     * Loads a Yaml file.
     *
     * @param mixed $file
     *   The resource
     */
    public function load(string $file): void
    {
        // Load from the file cache, fall back to loading the file.
        $content = $this->file_cache->get($file);
        if (!$content) {
            $content = $this->load_file($file);
            $this->file_cache->set($file, $content);
        }
        // Not supported.
        //$this->container->addResource(new FileResource($path));
        // empty file
        if (null === $content) {
            return;
        }
        // imports
        // Not supported.
        //$this->parseImports($content, $file);
        // parameters
        if (isset($content['parameters'])) {
            if (!is_array($content['parameters'])) {
                throw new InvalidArgumentException(sprintf('The "parameters" key should contain an array in %s. Check your YAML syntax.', $file));
            }
            foreach ($content['parameters'] as $key => $value) {
                $this->container->set_parameter($key, $this->resolve_services($value));
            }
        }
        // extensions
        // Not supported.
        //$this->loadFromExtensions($content);
        // services
        $this->parse_definitions($content, $file);
    }
    /**
     * Parses definitions
     */
    private function parse_definitions(array $content, string $file): void
    {
        if (!isset($content['services'])) {
            return;
        }
        if (!is_array($content['services'])) {
            throw new InvalidArgumentException(sprintf('The "services" key should contain an array in %s. Check your YAML syntax.', $file));
        }
        // Some extensions split up their dependencies into multiple files.
        if (isset($content['_provider'])) {
            $provider = $content['_provider'];
        } else {
            $basename = basename($file);
            [$provider] = explode('.', $basename, 2);
        }
        $defaults = $this->parse_defaults($content, $file);
        $defaults['tags'][] = ['name' => '_provider', 'provider' => $provider];
        foreach ($content['services'] as $id => $service) {
            $this->parse_definition($id, $service, $file, $defaults);
        }
    }
    /**
     *
     * @throws InvalidArgumentException
     */
    private function parse_defaults(array &$content, string $file): array
    {
        if (!\array_key_exists('_defaults', $content['services'])) {
            return [];
        }
        $defaults = $content['services']['_defaults'];
        unset($content['services']['_defaults']);
        if (!\is_array($defaults)) {
            throw new InvalidArgumentException(sprintf('Service "_defaults" key must be an array, "%s" given in "%s".', \gettype($defaults), $file));
        }
        foreach ($defaults as $key => $default) {
            if (!isset(self::DEFAULTS_KEYWORDS[$key])) {
                throw new InvalidArgumentException(sprintf('The configuration key "%s" cannot be used to define a default value in "%s". Allowed keys are "%s".', $key, $file, implode('", "', self::DEFAULTS_KEYWORDS)));
            }
        }
        if (isset($defaults['tags'])) {
            if (!\is_array($tags = $defaults['tags'])) {
                throw new InvalidArgumentException(sprintf('Parameter "tags" in "_defaults" must be an array in "%s". Check your YAML syntax.', $file));
            }
            foreach ($tags as $tag) {
                if (!\is_array($tag)) {
                    $tag = ['name' => $tag];
                }
                if (!isset($tag['name'])) {
                    throw new InvalidArgumentException(sprintf('A "tags" entry in "_defaults" is missing a "name" key in "%s".', $file));
                }
                $name = $tag['name'];
                unset($tag['name']);
                if (!\is_string($name) || '' === $name) {
                    throw new InvalidArgumentException(sprintf('The tag name in "_defaults" must be a non-empty string in "%s".', $file));
                }
                foreach ($tag as $attribute => $value) {
                    if (!is_scalar($value) && null !== $value) {
                        throw new InvalidArgumentException(sprintf('Tag "%s", attribute "%s" in "_defaults" must be of a scalar-type in "%s". Check your YAML syntax.', $name, $attribute, $file));
                    }
                }
            }
        }
        return $defaults;
    }
    /**
     * Parses a definition.
     *
     * @param array $service
     *
     * @throws InvalidArgumentException
     *   When tags are invalid.
     */
    private function parse_definition(string $id, $service, string $file, array $defaults): void
    {
        if (\is_string($service) && str_starts_with($service, '@')) {
            $this->container->set_alias($id, $alias = new Alias(substr($service, 1)));
            if (isset($defaults['public'])) {
                $alias->set_public($defaults['public']);
            }
            return;
        }
        if (null === $service) {
            $service = [];
        }
        if (!is_array($service)) {
            throw new InvalidArgumentException(sprintf('A service definition must be an array or a string starting with "@" but %s found for service "%s" in %s. Check your YAML syntax.', gettype($service), $id, $file));
        }
        if (isset($service['alias'])) {
            $this->container->set_alias($id, $alias = new Alias($service['alias']));
            if (isset($service['public'])) {
                $alias->set_public($service['public']);
            } elseif (isset($defaults['public'])) {
                $alias->set_public($defaults['public']);
            }
            if (array_key_exists('deprecated', $service)) {
                $deprecation = \is_array($service['deprecated']) ? $service['deprecated'] : ['message' => $service['deprecated']];
                $alias->set_deprecated($deprecation['package'] ?? '', $deprecation['version'] ?? '', $deprecation['message']);
            }
            return;
        }
        if (isset($service['parent'])) {
            $definition = new Child_Definition($service['parent']);
        } else {
            $definition = new Definition();
        }
        // Drupal services are public by default.
        $definition->set_public(true);
        if (isset($defaults['public'])) {
            $definition->set_public($defaults['public']);
        }
        if (isset($defaults['autowire'])) {
            $definition->set_autowired($defaults['autowire']);
        }
        if (isset($defaults['autoconfigure'])) {
            $definition->set_autoconfigured($defaults['autoconfigure']);
        }
        $definition->set_changes([]);
        if (isset($service['class'])) {
            $definition->set_class($service['class']);
        }
        if (isset($service['shared'])) {
            $definition->set_shared($service['shared']);
        }
        if (isset($service['synthetic'])) {
            $definition->set_synthetic($service['synthetic']);
        }
        if (isset($service['lazy'])) {
            $definition->set_lazy($service['lazy']);
        }
        if (isset($service['public'])) {
            $definition->set_public($service['public']);
        }
        if (isset($service['abstract'])) {
            $definition->set_abstract($service['abstract']);
        }
        if (array_key_exists('deprecated', $service)) {
            $deprecation = \is_array($service['deprecated']) ? $service['deprecated'] : ['message' => $service['deprecated']];
            $definition->set_deprecated($deprecation['package'] ?? '', $deprecation['version'] ?? '', $deprecation['message']);
        }
        if (isset($service['factory'])) {
            if (is_string($service['factory'])) {
                if (str_contains($service['factory'], ':') && !str_contains($service['factory'], '::')) {
                    $parts = explode(':', $service['factory']);
                    $definition->set_factory([$this->resolve_services('@' . $parts[0]), $parts[1]]);
                } else {
                    $definition->set_factory($service['factory']);
                }
            } else {
                $definition->set_factory([$this->resolve_services($service['factory'][0]), $service['factory'][1]]);
            }
        }
        if (isset($service['factory_class'])) {
            $definition->set_factory($service['factory_class']);
        }
        if (isset($service['factory_method'])) {
            $definition->set_factory($service['factory_method']);
        }
        if (isset($service['factory_service'])) {
            $definition->set_factory($service['factory_service']);
        }
        if (isset($service['file'])) {
            $definition->set_file($service['file']);
        }
        if (isset($service['arguments'])) {
            $definition->set_arguments($this->resolve_services($service['arguments']));
        }
        if (isset($service['properties'])) {
            $definition->set_properties($this->resolve_services($service['properties']));
        }
        if (isset($service['configurator'])) {
            if (is_string($service['configurator'])) {
                $definition->set_configurator($service['configurator']);
            } else {
                $definition->set_configurator([$this->resolve_services($service['configurator'][0]), $service['configurator'][1]]);
            }
        }
        if (isset($service['calls'])) {
            if (!is_array($service['calls'])) {
                throw new InvalidArgumentException(sprintf('Parameter "calls" must be an array for service "%s" in %s. Check your YAML syntax.', $id, $file));
            }
            foreach ($service['calls'] as $call) {
                if (isset($call['method'])) {
                    $method = $call['method'];
                    $args = isset($call['arguments']) ? $this->resolve_services($call['arguments']) : [];
                } else {
                    $method = $call[0];
                    $args = isset($call[1]) ? $this->resolve_services($call[1]) : [];
                }
                $definition->add_method_call($method, $args);
            }
        }
        $tags = $service['tags'] ?? [];
        if (!\is_array($tags)) {
            throw new InvalidArgumentException(sprintf('Parameter "tags" must be an array for service "%s" in "%s". Check your YAML syntax.', $id, $file));
        }
        if (isset($defaults['tags'])) {
            $tags = array_merge($tags, $defaults['tags']);
        }
        foreach ($tags as $tag) {
            if (!\is_array($tag)) {
                $tag = ['name' => $tag];
            }
            if (!isset($tag['name'])) {
                throw new InvalidArgumentException(sprintf('A "tags" entry is missing a "name" key for service "%s" in "%s".', $id, $file));
            }
            $name = $tag['name'];
            unset($tag['name']);
            if (!\is_string($name) || '' === $name) {
                throw new InvalidArgumentException(sprintf('The tag name for service "%s" in "%s" must be a non-empty string.', $id, $file));
            }
            foreach ($tag as $attribute => $value) {
                if (!is_scalar($value) && null !== $value) {
                    throw new InvalidArgumentException(sprintf('A "tags" attribute must be of a scalar-type for service "%s", tag "%s", attribute "%s" in "%s". Check your YAML syntax.', $id, $name, $attribute, $file));
                }
            }
            $definition->add_tag($name, $tag);
        }
        if (null !== $decorates = $service['decorates'] ?? null) {
            if ('' !== $decorates && '@' === $decorates[0]) {
                throw new InvalidArgumentException(\sprintf('The value of the "decorates" option for the "%s" service must be the id of the service without the "@" prefix (replace "%s" with "%s").', $id, $service['decorates'], substr($decorates, 1)));
            }
            $decoration_on_invalid = \array_key_exists('decoration_on_invalid', $service) ? $service['decoration_on_invalid'] : 'exception';
            if ('exception' === $decoration_on_invalid) {
                $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE;
            } elseif ('ignore' === $decoration_on_invalid) {
                $invalid_behavior = Container_Interface::IGNORE_ON_INVALID_REFERENCE;
            } elseif (null === $decoration_on_invalid) {
                $invalid_behavior = Container_Interface::NULL_ON_INVALID_REFERENCE;
            } elseif ('null' === $decoration_on_invalid) {
                throw new InvalidArgumentException(\sprintf('Invalid value "%s" for attribute "decoration_on_invalid" on service "%s". Did you mean null (without quotes) in "%s"?', $decoration_on_invalid, $id, $file));
            } else {
                throw new InvalidArgumentException(\sprintf('Invalid value "%s" for attribute "decoration_on_invalid" on service "%s". Did you mean "exception", "ignore" or null in "%s"?', $decoration_on_invalid, $id, $file));
            }
            $rename_id = $service['decoration_inner_name'] ?? null;
            $priority = $service['decoration_priority'] ?? 0;
            $definition->set_decorated_service($decorates, $rename_id, $priority, $invalid_behavior);
        }
        if (isset($service['autowire'])) {
            $definition->set_autowired($service['autowire']);
        }
        $this->container->set_definition($id, $definition);
    }
    /**
     * Loads a YAML file.
     *
     * @param string $file
     *
     * @return array The file content
     *
     * @throws InvalidArgumentException
     *   When the given file is not a local file or when it does not exist.
     */
    protected function load_file($file)
    {
        if (!stream_is_local($file)) {
            throw new InvalidArgumentException(sprintf('This is not a local file "%s".', $file));
        }
        if (!file_exists($file)) {
            throw new InvalidArgumentException(sprintf('The service file "%s" is not valid.', $file));
        }
        try {
            $valid_file = $this->validate(Yaml::decode(file_get_contents($file)), $file);
        } catch (Invalid_Data_Type_Exception $e) {
            throw new InvalidArgumentException(sprintf('The file "%s" does not contain valid YAML: ', $file) . $e->get_message());
        }
        return $valid_file;
    }
    /**
     * Validates a YAML file.
     *
     * @param mixed $content
     * @param string $file
     *
     * @return array
     *
     * @throws InvalidArgumentException
     *   When service file is not valid.
     */
    private function validate($content, $file): ?array
    {
        if (null === $content) {
            return $content;
        }
        if (!is_array($content)) {
            throw new InvalidArgumentException(sprintf('The service file "%s" is not valid. It should contain an array. Check your YAML syntax.', $file));
        }
        if ($invalid_keys = array_keys(array_diff_key($content, ['parameters' => 1, 'services' => 1]))) {
            throw new InvalidArgumentException(sprintf('The service file "%s" is not valid: it contains invalid root key(s) "%s". Services have to be added under "services" and Parameters under "parameters".', $file, implode('", "', $invalid_keys)));
        }
        return $content;
    }
    /**
     * Resolves services.
     *
     * @param string|array $value
     *
     * @return array|string|Reference
     */
    private function resolve_services(mixed $value): mixed
    {
        if ($value instanceof Tagged_Value) {
            $argument = $value->get_value();
            if (\in_array($value->get_tag(), ['tagged', 'tagged_iterator', 'tagged_locator'], true)) {
                $for_locator = 'tagged_locator' === $value->get_tag();
                if (\is_array($argument) && isset($argument['tag']) && $argument['tag']) {
                    if ($diff = array_diff(array_keys($argument), $supported_keys = ['tag', 'index_by', 'default_index_method', 'default_priority_method', 'exclude', 'exclude_self'])) {
                        throw new InvalidArgumentException(sprintf('"!%s" tag contains unsupported key "%s"; supported ones are "%s".', $value->get_tag(), implode('", "', $diff), implode('", "', $supported_keys)));
                    }
                    $argument = new Tagged_Iterator_Argument($argument['tag'], $argument['index_by'] ?? null, $argument['default_index_method'] ?? null, $for_locator, $argument['default_priority_method'] ?? null, (array) ($argument['exclude'] ?? null), $argument['exclude_self'] ?? true);
                } elseif (\is_string($argument) && $argument) {
                    $argument = new Tagged_Iterator_Argument($argument, null, null, $for_locator);
                } else {
                    throw new InvalidArgumentException(sprintf('"!%s" tags only accept a non empty string or an array with a key "tag"".', $value->get_tag()));
                }
                if ($for_locator) {
                    return new Service_Locator_Argument($argument);
                }
                return $argument;
            }
            if ($value->get_tag() === 'service_closure') {
                return new Service_Closure_Argument($this->resolve_services($argument));
            }
        }
        if (is_array($value)) {
            $value = array_map($this->resolve_services(...), $value);
        } elseif (is_string($value) && str_starts_with($value, '@=')) {
            // Not supported.
            //return new Expression(substr($value, 2));
            throw new InvalidArgumentException(sprintf("'%s' is an Expression, but expressions are not supported.", $value));
        } elseif (is_string($value) && str_starts_with($value, '@')) {
            if (str_starts_with($value, '@>')) {
                $argument = $this->resolve_services(substr_replace($value, '', 1, 1));
                return new Service_Closure_Argument($argument);
            }
            if (str_starts_with($value, '@@')) {
                $value = substr($value, 1);
                $invalid_behavior = null;
            } elseif (str_starts_with($value, '@?')) {
                $value = substr($value, 2);
                $invalid_behavior = Container_Interface::IGNORE_ON_INVALID_REFERENCE;
            } else {
                $value = substr($value, 1);
                $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE;
            }
            if (str_ends_with($value, '=')) {
                $value = substr($value, 0, -1);
            }
            if (null !== $invalid_behavior) {
                $value = new Reference($value, $invalid_behavior);
            }
        }
        return $value;
    }
}