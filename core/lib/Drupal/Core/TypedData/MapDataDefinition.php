<?php

declare(strict_types=1);

namespace Drupal\Core\TypedData;

/**
 * A typed data definition class for defining maps.
 */
class MapDataDefinition extends ComplexDataDefinitionBase
{
    /**
     * The name of the main property, or NULL if there is none.
     *
     * @var string
     */
    protected $mainPropertyName;

    /**
     * Creates a new map definition.
     *
     * @param string $type
     *   (optional) The data type of the map. Defaults to 'map'.
     */
    public static function create($type = 'map'): static
    {
        $definition['type'] = $type;
        return new static($definition);
    }

    /**
     * {@inheritdoc}
     */
    public static function createFromDataType($data_type): static
    {
        return static::create($data_type);
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyDefinitions()
    {
        if (!isset($this->propertyDefinitions)) {
            $this->propertyDefinitions = [];
        }
        return $this->propertyDefinitions;
    }

    /**
     * Sets the definition of a map property.
     *
     * @param string $name
     *   The name of the property to define.
     * @param \Drupal\Core\TypedData\DataDefinitionInterface|null $definition
     *   (optional) The property definition to set, or NULL to unset it.
     *
     * @return $this
     */
    public function setPropertyDefinition($name, ?DataDefinitionInterface $definition = null): static
    {
        if (isset($definition)) {
            $this->propertyDefinitions[$name] = $definition;
        } else {
            unset($this->propertyDefinitions[$name]);
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMainPropertyName()
    {
        return $this->mainPropertyName;
    }

    /**
     * Sets the main property name.
     *
     * @param string|null $name
     *   The name of the main property, or NULL if there is none.
     *
     * @return $this
     */
    public function setMainPropertyName($name): static
    {
        $this->mainPropertyName = $name;
        return $this;
    }

}
