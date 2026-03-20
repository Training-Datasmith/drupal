<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Statement;

/**
 * A trait for calling \PDOStatement methods.
 */
trait Pdo_Trait
{
    /**
     * Map FETCH_* modes to their literal for inclusion in messages.
     *
     * @see https://github.com/php/php-src/blob/master/ext/pdo/php_pdo_driver.h#L65-L80
     */
    protected array $fetch_mode_literals = [\PDO::FETCH_DEFAULT => 'FETCH_DEFAULT', \PDO::FETCH_LAZY => 'FETCH_LAZY', \PDO::FETCH_ASSOC => 'FETCH_ASSOC', \PDO::FETCH_NUM => 'FETCH_NUM', \PDO::FETCH_BOTH => 'FETCH_BOTH', \PDO::FETCH_OBJ => 'FETCH_OBJ', \PDO::FETCH_BOUND => 'FETCH_BOUND', \PDO::FETCH_COLUMN => 'FETCH_COLUMN', \PDO::FETCH_CLASS => 'FETCH_CLASS', \PDO::FETCH_INTO => 'FETCH_INTO', \PDO::FETCH_FUNC => 'FETCH_FUNC', \PDO::FETCH_NAMED => 'FETCH_NAMED', \PDO::FETCH_KEY_PAIR => 'FETCH_KEY_PAIR', \PDO::FETCH_CLASS | \PDO::FETCH_CLASSTYPE => 'FETCH_CLASS | FETCH_CLASSTYPE', \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE => 'FETCH_CLASS | FETCH_PROPS_LATE'];
    /**
     * Converts a FetchAs mode to a \PDO::FETCH_* constant value.
     *
     * @param \Drupal\Core\Database\Statement\FetchAs $mode
     *   The FetchAs mode.
     *
     * @return int
     *   A \PDO::FETCH_* constant value.
     */
    protected function fetch_as_to_pdo(Fetch_As $mode): int
    {
        return match ($mode) {
            Fetch_As::Associative => \PDO::FETCH_ASSOC,
            Fetch_As::ClassObject => \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE,
            Fetch_As::Column => \PDO::FETCH_COLUMN,
            Fetch_As::List => \PDO::FETCH_NUM,
            Fetch_As::Object => \PDO::FETCH_OBJ,
        };
    }
    /**
     * Converts a \PDO::FETCH_* constant value to a FetchAs mode.
     *
     * @param int $mode
     *   The \PDO::FETCH_* constant value.
     *
     * @return \Drupal\Core\Database\Statement\FetchAs
     *   A FetchAs mode.
     */
    protected function pdo_to_fetch_as(int $mode): Fetch_As
    {
        return match ($mode) {
            \PDO::FETCH_ASSOC => Fetch_As::Associative,
            \PDO::FETCH_CLASS, \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE => Fetch_As::ClassObject,
            \PDO::FETCH_COLUMN => Fetch_As::Column,
            \PDO::FETCH_NUM => Fetch_As::List,
            \PDO::FETCH_OBJ => Fetch_As::Object,
            default => throw new \RuntimeException('Fetch mode ' . ($this->fetch_mode_literals[$mode] ?? $mode) . ' is not supported. Use supported modes only.'),
        };
    }
    /**
     * Returns the client-level database statement object.
     *
     * This method should normally be used only within database driver code.
     *
     * @return object
     *   The client-level database statement.
     *
     * @throws \RuntimeException
     *   If the client-level statement is not set.
     */
    abstract public function get_client_statement(): object;
    /**
     * Sets the default fetch mode for the PDO statement.
     *
     * @param \Drupal\Core\Database\Statement\FetchAs $mode
     *   One of the cases of the FetchAs enum.
     * @param int|class-string|null $columnOrClass
     *   If $mode is FetchAs::Column, the index of the column to fetch.
     *   If $mode is FetchAs::ClassObject, the FQCN of the object.
     * @param list<mixed>|null $constructorArguments
     *   If $mode is FetchAs::ClassObject, the arguments to pass to the
     *   constructor.
     *
     * @return bool
     *   Returns true on success or false on failure.
     */
    protected function client_set_fetch_mode(Fetch_As $mode, int|string|null $column_or_class = null, array|null $constructor_arguments = null): bool
    {
        return match ($mode) {
            Fetch_As::Column => $this->get_client_statement()->set_fetch_mode(\PDO::FETCH_COLUMN, $column_or_class ?? $this->fetch_options['column']),
            Fetch_As::ClassObject => $this->get_client_statement()->set_fetch_mode(\PDO::FETCH_CLASS, $column_or_class ?? $this->fetch_options['class'], $constructor_arguments ?? $this->fetch_options['constructor_args']),
            default => $this->get_client_statement()->set_fetch_mode($this->fetch_as_to_pdo($mode)),
        };
    }
    /**
     * Executes the prepared PDO statement.
     *
     * @param array|null $arguments
     *   An array of values with as many elements as there are bound parameters in
     *   the SQL statement being executed. This can be NULL.
     * @param array $options
     *   An array of options for this query.
     *
     * @return bool
     *   TRUE on success, or FALSE on failure.
     */
    protected function client_execute(?array $arguments = [], array $options = []): bool
    {
        return $this->get_client_statement()->execute($arguments);
    }
    /**
     * Fetches the next row from the PDO statement.
     *
     * @param \Drupal\Core\Database\Statement\FetchAs|null $mode
     *   (Optional) one of the cases of the FetchAs enum. If not specified,
     *   defaults to what is specified by setFetchMode().
     * @param int|null $cursorOrientation
     *   Not implemented in all database drivers, don't use.
     * @param int|null $cursorOffset
     *   Not implemented in all database drivers, don't use.
     *
     * @return array<scalar|null>|object|scalar|null|false
     *   A result, formatted according to $mode, or FALSE on failure.
     */
    protected function client_fetch(?Fetch_As $mode = null, ?int $cursor_orientation = null, ?int $cursor_offset = null): array|object|int|float|string|bool|null
    {
        return match (func_num_args()) {
            0 => $this->get_client_statement()->fetch(),
            1 => $this->get_client_statement()->fetch($this->fetch_as_to_pdo($mode)),
            2 => $this->get_client_statement()->fetch($this->fetch_as_to_pdo($mode), $cursor_orientation),
            default => $this->get_client_statement()->fetch($this->fetch_as_to_pdo($mode), $cursor_orientation, $cursor_offset),
        };
    }
    /**
     * Returns a single column from the next row of a result set.
     *
     * @param int $column
     *   0-indexed number of the column to retrieve from the row. If no value is
     *   supplied, the first column is fetched.
     *
     * @return scalar|null|false
     *   A single column from the next row of a result set or false if there are
     *   no more rows.
     */
    protected function client_fetch_column(int $column = 0): int|float|string|bool|null
    {
        return $this->get_client_statement()->fetch_column($column);
    }
    /**
     * Fetches the next row and returns it as an object.
     *
     * @param class-string|null $class
     *   FQCN of the class to be instantiated.
     * @param list<mixed>|null $constructorArguments
     *   The arguments to be passed to the constructor.
     *
     * @return object|false
     *   An instance of the required class with property names that correspond
     *   to the column names, or FALSE on failure.
     */
    protected function client_fetch_object(?string $class = null, array $constructor_arguments = []): object|false
    {
        if ($class) {
            return $this->get_client_statement()->fetch_object($class, $constructor_arguments);
        }
        return $this->get_client_statement()->fetch_object();
    }
    /**
     * Returns an array containing all of the result set rows.
     *
     * @param \Drupal\Core\Database\Statement\FetchAs|null $mode
     *   (Optional) one of the cases of the FetchAs enum. If not specified,
     *   defaults to what is specified by setFetchMode().
     * @param int|class-string|null $columnOrClass
     *   If $mode is FetchAs::Column, the index of the column to fetch.
     *   If $mode is FetchAs::ClassObject, the FQCN of the object.
     * @param list<mixed>|null $constructorArguments
     *   If $mode is FetchAs::ClassObject, the arguments to pass to the
     *   constructor.
     *
     * @return array<array<scalar|null>|object|scalar|null>
     *   An array of results.
     */
    protected function client_fetch_all(?Fetch_As $mode = null, int|string|null $column_or_class = null, array|null $constructor_arguments = null): array
    {
        return match ($mode) {
            Fetch_As::Column => $this->get_client_statement()->fetch_all(\PDO::FETCH_COLUMN, $column_or_class ?? $this->fetch_options['column']),
            Fetch_As::ClassObject => $this->get_client_statement()->fetch_all(\PDO::FETCH_CLASS, $column_or_class ?? $this->fetch_options['class'], $constructor_arguments ?? $this->fetch_options['constructor_args']),
            default => $this->get_client_statement()->fetch_all($this->fetch_as_to_pdo($mode ?? $this->fetch_mode)),
        };
    }
    /**
     * Returns the number of rows affected by the last SQL statement.
     *
     * @return int
     *   The number of rows.
     */
    protected function client_row_count(): int
    {
        return $this->get_client_statement()->row_count();
    }
    /**
     * Returns the query string used to prepare the statement.
     *
     * @return string
     *   The query string.
     */
    protected function client_query_string(): string
    {
        return $this->get_client_statement()->query_string;
    }
}