<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Attribute;

/**
 * Defines a common interface for classed attributes.
 */
interface Attribute_Interface
{
    /**
     * Gets the value of an attribute.
     */
    public function get(): mixed;
    /**
     * Gets the name of the provider of the attribute class.
     *
     * @return string|null
     *   The provider of the attribute class.
     */
    public function get_provider(): ?string;
    /**
     * Sets the name of the provider of the attribute class.
     *
     * @param string $provider
     *   The provider of the annotated class.
     */
    public function set_provider(string $provider): void;
    /**
     * Gets the unique ID for this attribute class.
     *
     * @return string
     *   The attribute class ID.
     */
    public function get_id(): string;
    /**
     * Gets the class of the attribute class.
     *
     * @return class-string|null
     *   The attribute class.
     */
    public function get_class(): ?string;
    /**
     * Sets the class of the attributed class.
     *
     * @param class-string $class
     *   The class of the attributed class.
     */
    public function set_class(string $class): void;
    /**
     * Gets the dependencies for this attribute class.
     *
     * @return array{"class"?: list<class-string>, "interface"?: list<class-string>, "trait"?: list<class-string>, "provider"?: list<string>}|null
     *   The list of dependencies, keyed by type. If the type is 'class', 'trait',
     *   or 'interface', the values for the type are class names. If the type is
     *   'provider', the values for the type are provider names.
     */
    public function get_dependencies(): ?array;
    /**
     * Sets the dependencies for this attribute class.
     *
     * @param array{"class"?: list<class-string>, "interface"?: list<class-string>, "trait"?: list<class-string>, "provider"?: list<string>}|null $dependencies
     *   The list of dependencies, keyed by type. If the type is 'class', 'trait',
     *   or 'interface', the values for the type are class names. If the type is
     *   'provider', the values for the type are provider names.
     */
    public function set_dependencies(?array $dependencies): void;
}