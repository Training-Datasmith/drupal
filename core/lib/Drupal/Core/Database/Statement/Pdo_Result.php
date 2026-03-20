<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Statement;

/**
 * Class for PDO-provided results of a data query language (DQL) statement.
 */
class Pdo_Result extends Result_Base
{
    use Pdo_Trait;
    /**
     * Constructor.
     *
     * @param \Drupal\Core\Database\Statement\FetchAs $fetchMode
     *   The fetch mode.
     * @param array{class: class-string, constructor_args: list<mixed>, column: int, cursor_orientation?: int, cursor_offset?: int} $fetchOptions
     *   The fetch options.
     * @param \PDOStatement $clientStatement
     *   The PDO Statement object. PDO does not provide a separate object for
     *   results, se we need to fetch data from the Statement.
     */
    public function __construct(Fetch_As $fetch_mode, array $fetch_options, protected readonly \PDOStatement $client_statement)
    {
        parent::__construct($fetch_mode, $fetch_options);
    }
    /**
     * Returns the client-level database PDO statement object.
     *
     * This method should normally be used only within database driver code.
     *
     * @return \PDOStatement
     *   The client-level database PDO statement.
     */
    public function get_client_statement(): \PDOStatement
    {
        return $this->client_statement;
    }
    /**
     * {@inheritdoc}
     */
    public function row_count(): ?int
    {
        return $this->client_row_count();
    }
    /**
     * {@inheritdoc}
     */
    public function set_fetch_mode(Fetch_As $mode, array $fetch_options): bool
    {
        return match ($mode) {
            Fetch_As::ClassObject => $this->client_set_fetch_mode($mode, $fetch_options['class'], $fetch_options['constructor_args'] ?? null),
            Fetch_As::Column => $this->client_set_fetch_mode($mode, $fetch_options['column']),
            default => $this->client_set_fetch_mode($mode),
        };
    }
    /**
     * {@inheritdoc}
     */
    public function fetch(Fetch_As $mode, array $fetch_options): array|object|int|float|string|bool|null
    {
        if (!empty($fetch_options)) {
            $this->set_fetch_mode($mode, $fetch_options);
        }
        if (isset($fetch_options['cursor_orientation'])) {
            if (isset($fetch_options['cursor_offset'])) {
                return $this->client_fetch($mode, $fetch_options['cursor_orientation'], $fetch_options['cursor_offset']);
            }
            return $this->client_fetch($mode, $fetch_options['cursor_orientation']);
        }
        return $this->client_fetch($mode);
    }
    /**
     * {@inheritdoc}
     */
    public function fetch_all(Fetch_As $mode, array $fetch_options): array
    {
        return $this->client_fetch_all($mode, $fetch_options['column'] ?? $fetch_options['class'] ?? null, $fetch_options['constructor_args'] ?? null);
    }
}