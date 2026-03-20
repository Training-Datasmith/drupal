<?php

declare (strict_types=1);
namespace Drupal\Component\Dependency_Injection\Dumper;

use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * PhpArrayDumper dumps a service container as a PHP array.
 *
 * The format of this dumper is a human-readable serialized PHP array, which is
 * very similar to the YAML based format, but based on PHP arrays instead of
 * YAML strings.
 *
 * It is human-readable, for a machine-optimized version based on this one see
 * \Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper.
 *
 * @see \Drupal\Component\DependencyInjection\PhpArrayContainer
 */
class Php_Array_Dumper extends Optimized_Php_Array_Dumper
{
    /**
     * {@inheritdoc}
     */
    public function get_array()
    {
        $this->serialize = false;
        return parent::get_array();
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    protected function dump_collection($collection, &$resolve = false): array
    {
        $code = [];
        foreach ($collection as $key => $value) {
            if (is_array($value)) {
                $code[$key] = $this->dump_collection($value);
            } else {
                $code[$key] = $this->dump_value($value);
            }
        }
        return $code;
    }
    /**
     * {@inheritdoc}
     */
    protected function get_service_call($id, $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE): string
    {
        if ($invalid_behavior !== Container_Interface::EXCEPTION_ON_INVALID_REFERENCE) {
            return '@?' . $id;
        }
        return '@' . $id;
    }
    /**
     * {@inheritdoc}
     */
    protected function get_parameter_call($name): string
    {
        return '%' . $name . '%';
    }
    /**
     * {@inheritdoc}
     */
    protected function supports_machine_format(): bool
    {
        return false;
    }
}