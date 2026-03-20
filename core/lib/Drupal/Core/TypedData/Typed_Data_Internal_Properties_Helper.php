<?php

declare(strict_types=1);

namespace Drupal\Core\TypedData;

/**
 * Helper class for internal properties.
 */
class TypedDataInternalPropertiesHelper
{
    /**
     * Gets an array non-internal properties from a complex data object.
     *
     * @param \Drupal\Core\TypedData\ComplexDataInterface $data
     *   The complex data object.
     *
     * @return \Drupal\Core\TypedData\TypedDataInterface[]
     *   The non-internal properties, keyed by property name.
     */
    public static function getNonInternalProperties(ComplexDataInterface $data): array
    {
        return array_filter($data->getProperties(true), fn (TypedDataInterface $property) => !$property->getDataDefinition()->isInternal());
    }

}
