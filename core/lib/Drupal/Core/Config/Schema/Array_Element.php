<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Schema;

use Drupal\Core\Typed_Data\Complex_Data_Interface;
/**
 * Defines a generic configuration element that contains multiple properties.
 *
 * @implements \IteratorAggregate<string, \Drupal\Core\TypedData\TypedDataInterface>
 */
abstract class Array_Element extends Element implements \IteratorAggregate, Typed_Config_Interface, Complex_Data_Interface
{
    /**
     * Parsed elements.
     *
     * @var array<string, \Drupal\Core\TypedData\TypedDataInterface>
     */
    protected $elements;
    /**
     * Determines if there is a translatable value.
     *
     * @return bool
     *   Returns true if a translatable element is found.
     */
    public function has_translatable_elements(): bool
    {
        foreach ($this as $element) {
            // Early return if found.
            if ($element->get_data_definition()['translatable'] === true) {
                return true;
            }
            if ($element instanceof Array_Element && $element->has_translatable_elements()) {
                return true;
            }
        }
        return false;
    }
    /**
     * Gets valid configuration data keys.
     *
     * @return array
     *   Array of valid configuration data keys.
     */
    protected function get_all_keys()
    {
        return is_array($this->value) ? array_keys($this->value) : [];
    }
    /**
     * Builds an array of contained elements.
     *
     * @return \Drupal\Core\TypedData\TypedDataInterface[]
     *   An array of elements contained in this element.
     */
    protected function parse()
    {
        $elements = [];
        foreach ($this->get_all_keys() as $key) {
            $value = $this->value[$key] ?? null;
            $definition = $this->get_element_definition($key);
            $elements[$key] = $this->create_element($definition, $value, $key);
        }
        return $elements;
    }
    /**
     * Gets data definition object for contained element.
     *
     * @param int|string $key
     *   Property name or index of the element.
     *
     * @return \Drupal\Core\TypedData\DataDefinitionInterface
     *   The data definition object for the property.
     */
    abstract protected function get_element_definition($key);
    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        $parts = explode('.', $name);
        $root_key = array_shift($parts);
        $elements = $this->get_elements();
        if (isset($elements[$root_key])) {
            $element = $elements[$root_key];
            // If $property_name contained a dot recurse into the keys.
            while ($element && ($key = array_shift($parts)) !== null) {
                if ($element instanceof Typed_Config_Interface) {
                    $element = $element->get($key);
                } else {
                    $element = null;
                }
            }
        }
        if (isset($element)) {
            return $element;
        }
        throw new \InvalidArgumentException("The configuration property {$name} doesn't exist.");
    }
    /**
     * {@inheritdoc}
     */
    public function get_elements()
    {
        if (!isset($this->elements)) {
            $this->elements = $this->parse();
        }
        return $this->elements;
    }
    /**
     * {@inheritdoc}
     */
    public function is_empty()
    {
        return empty($this->value);
    }
    /**
     * {@inheritdoc}
     */
    public function to_array()
    {
        return $this->value ?? [];
    }
    /**
     * {@inheritdoc}
     */
    public function on_change($name): void
    {
        // Notify the parent of changes.
        if (isset($this->parent)) {
            $this->parent->on_change($this->name);
        }
    }
    /**
     * Retrieves the iterator for the object.
     *
     * @return \ArrayIterator<string, \Drupal\Core\TypedData\TypedDataInterface>
     *   The iterator.
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->get_elements());
    }
    /**
     * Creates a contained typed configuration object.
     *
     * @param \Drupal\Core\TypedData\DataDefinitionInterface $definition
     *   The data definition object.
     * @param mixed $value
     *   (optional) The data value. If set, it has to match one of the supported
     *   data type format as documented for the data type classes.
     * @param string $key
     *   The key of the contained element.
     *
     * @return \Drupal\Core\TypedData\TypedDataInterface
     *   A typed data object created from the given parameters.
     */
    protected function create_element(\Drupal\Core\Typed_Data\Data_Definition_Interface $definition, $value, $key)
    {
        return $this->get_typed_data_manager()->create($definition, $value, $key, $this);
    }
    /**
     * Creates a new data definition object from an array and configuration.
     *
     * @param array $definition
     *   The base type definition array, for which a data definition should be
     *   created.
     * @param mixed $value
     *   The value of the configuration element.
     * @param string $key
     *   The key of the contained element.
     *
     * @return \Drupal\Core\TypedData\DataDefinitionInterface
     *   A data definition object for the given parameters.
     */
    protected function build_data_definition(array $definition, $value, $key)
    {
        return $this->get_typed_data_manager()->build_data_definition($definition, $value, $key, $this);
    }
    /**
     * Determines if this element allows NULL as a value.
     *
     * @return bool
     *   TRUE if NULL is a valid value, FALSE otherwise.
     */
    public function is_nullable()
    {
        return isset($this->definition['nullable']) && $this->definition['nullable'] == true;
    }
    /**
     * {@inheritdoc}
     */
    public function set($property_name, $value, $notify = true)
    {
        $this->value[$property_name] = $value;
        // Config schema elements do not make use of notifications. Thus, we skip
        // notifying parents.
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_properties($include_computed = false)
    {
        $properties = [];
        foreach (array_keys($this->value) as $name) {
            $properties[$name] = $this->get($name);
        }
        return $properties;
    }
}