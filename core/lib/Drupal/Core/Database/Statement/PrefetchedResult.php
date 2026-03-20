<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Statement;

use Drupal\Core\Database\Fetch_Mode_Trait;
/**
 * Class for prefetched results of a data query language (DQL) statement.
 */
class Prefetched_Result extends Result_Base
{
    use Fetch_Mode_Trait;
    /**
     * The column names.
     */
    public readonly array $column_names;
    /**
     * The current row index in the result set.
     */
    protected ?int $current_row_index = null;
    /**
     * Constructor.
     *
     * @param \Drupal\Core\Database\Statement\FetchAs $fetchMode
     *   The fetch mode.
     * @param array{class: class-string, constructor_args: list<mixed>, column: int, cursor_orientation?: int, cursor_offset?: int} $fetchOptions
     *   The fetch options.
     * @param array $data
     *   The prefetched data, in FetchAs::Associative format.
     * @param int|null $rowCount
     *   The row count.
     */
    public function __construct(Fetch_As $fetch_mode, array $fetch_options, protected array $data, public readonly ?int $row_count)
    {
        parent::__construct($fetch_mode, $fetch_options);
        $this->column_names = isset($this->data[0]) ? array_keys($this->data[0]) : [];
        $this->current_row_index = -1;
    }
    /**
     * {@inheritdoc}
     */
    public function row_count(): ?int
    {
        return $this->row_count;
    }
    /**
     * {@inheritdoc}
     */
    public function set_fetch_mode(Fetch_As $mode, array $fetch_options): bool
    {
        // We do not really need to do anything here, since calls to any of this
        // class' methods require an explicit fetch mode to be passed in, and we
        // have no longer an active client statement to which we may want to pass
        // the default fetch mode. Just return TRUE.
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function fetch(Fetch_As $mode, array $fetch_options): array|object|int|float|string|bool|null
    {
        $this->current_row_index++;
        if (!isset($this->data[$this->current_row_index])) {
            $this->current_row_index = null;
            return false;
        }
        $row_assoc = $this->data[$this->current_row_index];
        unset($this->data[$this->current_row_index]);
        return $this->assoc_to_fetch_mode($row_assoc, $mode, $fetch_options);
    }
    /**
     * {@inheritdoc}
     */
    public function fetch_all_keyed(int $key_index = 0, int $value_index = 1): array
    {
        if (!isset($this->column_names[$key_index]) || !isset($this->column_names[$value_index])) {
            return [];
        }
        $key = $this->column_names[$key_index];
        $value = $this->column_names[$value_index];
        $result = [];
        while ($row = $this->fetch(Fetch_As::Associative, $this->fetch_options)) {
            $result[$row[$key]] = $row[$value];
        }
        return $result;
    }
}