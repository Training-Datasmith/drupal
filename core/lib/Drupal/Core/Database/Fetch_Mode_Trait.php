<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

use Drupal\Core\Database\Statement\Fetch_As;
/**
 * Provide helper methods for statement fetching.
 */
trait Fetch_Mode_Trait
{
    /**
     * Converts a row of data in associative format to list.
     *
     * @param array $rowAssoc
     *   A row of data in associative format.
     *
     * @return array
     *   The row in list format.
     */
    protected function assoc_to_num(array $row_assoc): array
    {
        return array_values($row_assoc);
    }
    /**
     * Converts a row of data in associative format to object.
     *
     * @param array $rowAssoc
     *   A row of data in associative format.
     *
     * @return object
     *   The row in object format.
     */
    protected function assoc_to_obj(array $row_assoc): \stdClass
    {
        return (object) $row_assoc;
    }
    /**
     * Converts a row of data in associative format to classed object.
     *
     * @param array $rowAssoc
     *   A row of data in associative format.
     * @param string $className
     *   Name of the created class.
     * @param array $constructorArguments
     *   Elements of this array are passed to the constructor.
     *
     * @return object
     *   The row in classed object format.
     */
    protected function assoc_to_class(array $row_assoc, string $class_name, array $constructor_arguments): object
    {
        $class_obj = new $class_name(...$constructor_arguments);
        foreach ($row_assoc as $column => $value) {
            $class_obj->{$column} = $value;
        }
        return $class_obj;
    }
    /**
     * Converts a row of data in associative format to column.
     *
     * @param array $rowAssoc
     *   A row of data in associative format.
     * @param string[] $columnNames
     *   The list of the row columns.
     * @param int $columnIndex
     *   The index of the column to fetch the value of.
     *
     * @return string
     *   The value of the column.
     *
     * @throws \ValueError
     *   If the column index is not defined.
     */
    protected function assoc_to_column(array $row_assoc, array $column_names, int $column_index): mixed
    {
        if (!isset($column_names[$column_index])) {
            throw new \Value_Error('Invalid column index');
        }
        return $row_assoc[$column_names[$column_index]];
    }
    /**
     * Converts a row of data in associative format to a specified format.
     *
     * @param array $rowAssoc
     *   A row of data in FetchAs::Associative format.
     * @param \Drupal\Core\Database\Statement\FetchAs $mode
     *   The target target mode.
     * @param array $fetchOptions
     *   The fetch mode options.
     *
     * @return array<scalar|null>|object|scalar|null|false
     *   The data in the target mode.
     *
     * @throws \ValueError
     *   If the column index is not defined.
     */
    protected function assoc_to_fetch_mode(array $row_assoc, Fetch_As $mode, array $fetch_options): array|object|int|float|string|bool|null
    {
        return match ($mode) {
            Fetch_As::Associative => $row_assoc,
            Fetch_As::ClassObject => $this->assoc_to_class($row_assoc, $fetch_options['class'], $fetch_options['constructor_args']),
            Fetch_As::Column => $this->assoc_to_column($row_assoc, array_keys($row_assoc), $fetch_options['column']),
            Fetch_As::List => $this->assoc_to_num($row_assoc),
            Fetch_As::Object => $this->assoc_to_obj($row_assoc),
        };
    }
}