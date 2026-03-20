<?php

declare (strict_types=1);
namespace Drupal\Component\Utility;

/**
 * Provides helper methods for reflection.
 */
final class Reflection
{
    /**
     * Gets the parameter's class name.
     *
     * @param \ReflectionParameter $parameter
     *   The parameter.
     *
     * @return string|null
     *   The parameter's class name or NULL if the parameter is not a class.
     */
    public static function get_parameter_class_name(\ReflectionParameter $parameter): ?string
    {
        $name = null;
        $parameter_type = $parameter->get_type();
        if ($parameter_type instanceof \ReflectionNamedType && !$parameter_type->is_builtin()) {
            $name = $parameter_type->get_name();
            $lc_name = strtolower($name);
            switch ($lc_name) {
                case 'self':
                    return $parameter->get_declaring_class()->get_name();
                case 'parent':
                    return ($parent = $parameter->get_declaring_class()->get_parent_class()) ? $parent->name : null;
            }
        }
        return $name;
    }
}