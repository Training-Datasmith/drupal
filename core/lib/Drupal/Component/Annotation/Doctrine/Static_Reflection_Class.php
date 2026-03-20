<?php

declare (strict_types=1);
// phpcs:ignoreFile
/**
 * @file
 *
 * This class is a near-copy of
 * Doctrine\Common\Reflection\StaticReflectionClass, which is part of the
 * Doctrine project: <http://www.doctrine-project.org>. It was copied from
 * version 1.2.2.
 *
 * Original copyright:
 *
 * Copyright (c) 2006-2015 Doctrine Project
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 */
namespace Drupal\Component\Annotation\Doctrine;

use ReflectionClass;
use Reflection_Exception;
class Static_Reflection_Class extends ReflectionClass
{
    public function __construct(
        /**
         * The static reflection parser object.
         */
        private readonly Static_Reflection_Parser $static_reflection_parser
    )
    {
    }
    /**
     * {@inheritDoc}
     */
    public function get_name(): string
    {
        return $this->static_reflection_parser->get_class_name();
    }
    /**
     * {@inheritDoc}
     */
    public function get_doc_comment(): string|false
    {
        return $this->static_reflection_parser->get_doc_comment();
    }
    /**
     * {@inheritDoc}
     */
    public function get_namespace_name(): string
    {
        return $this->static_reflection_parser->get_namespace_name();
    }
    /**
     * @return string[]
     */
    public function get_use_statements()
    {
        return $this->static_reflection_parser->get_use_statements();
    }
    /**
     * Determines if the class has the provided class attribute.
     *
     * @param string $attribute The attribute to check for.
     */
    public function has_class_attribute(string $attribute): bool
    {
        return $this->static_reflection_parser->has_class_attribute($attribute);
    }
    /**
     * {@inheritDoc}
     */
    public function get_method($name): \ReflectionMethod
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_property($name): \ReflectionProperty
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public static function export($argument, $return = false): never
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_constant($name): mixed
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_constructor(): ?\ReflectionMethod
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_default_properties(): array
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_end_line(): int|false
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_extension(): ?\ReflectionExtension
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_extension_name(): string|false
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_file_name(): string|false
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_interface_names(): array
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_interfaces(): array
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_methods($filter = null): array
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_modifiers(): int
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_parent_class(): \ReflectionClass|false
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_properties($filter = null): array
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_short_name(): string
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_start_line(): int|false
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_static_properties(): array
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_static_property_value($name, $default = ''): mixed
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_trait_aliases(): array
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_trait_names(): array
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_traits(): array
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function has_constant($name): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function has_method($name): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function has_property($name): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function implements_interface($interface): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function in_namespace(): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function is_abstract(): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function is_cloneable(): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function is_final(): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function is_instance($object): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function is_instantiable(): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function is_interface(): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function is_internal(): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function is_iterateable(): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function is_subclass_of($class): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function is_trait(): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function is_user_defined(): bool
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function new_instance_args(array $args = []): ?object
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function new_instance_without_constructor(): object
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function set_static_property_value($name, $value): void
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function get_constants(?int $filter = null): array
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function new_instance(mixed ...$args): object
    {
        throw new Reflection_Exception('Method not implemented');
    }
    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        throw new Reflection_Exception('Method not implemented');
    }
}