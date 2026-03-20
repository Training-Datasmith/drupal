<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * StatementInterface iterator trait.
 *
 * Implements the methods required by StatementInterface objects that implement
 * the \Iterator interface.
 */
trait Statement_Iterator_Trait
{
    /**
     * Traces if rows can be fetched from the resultset.
     */
    private bool $is_resultset_iterable = false;
    /**
     * The current row, retrieved in the current fetch format.
     */
    private mixed $resultset_row = null;
    /**
     * The key of the current row.
     *
     * This keeps the index of rows fetched from the underlying statement. It is
     * set to -1 when no rows have been fetched yet.
     */
    private int $resultset_key = -1;
    /**
     * Informs the iterator whether rows can be fetched from the resultset.
     *
     * @param bool $valid
     *   The result of the execution of the client statement.
     */
    protected function mark_resultset_iterable(bool $valid): void
    {
        $this->is_resultset_iterable = $valid;
        $this->resultset_row = null;
        if ($valid === true) {
            $this->resultset_key = -1;
        }
    }
    /**
     * Sets the current resultset row for the iterator, and increments the key.
     *
     * @param mixed $row
     *   The last row fetched from the client statement.
     */
    protected function set_resultset_current_row(mixed $row): void
    {
        $this->resultset_row = $row;
        $this->resultset_key++;
    }
    /**
     * Returns the row index of the current element in the resultset.
     *
     * @return int
     *   The row index of the current element in the resultset.
     */
    protected function get_resultset_current_row_index(): int
    {
        return $this->resultset_key;
    }
    /**
     * Informs the iterator that no more rows can be fetched from the resultset.
     */
    protected function mark_resultset_fetching_complete(): void
    {
        $this->mark_resultset_iterable(false);
    }
    /**
     * Returns the current element.
     *
     * @see https://www.php.net/manual/en/iterator.current.php
     *
     * @internal This method should not be called directly.
     */
    public function current(): mixed
    {
        return $this->resultset_row;
    }
    /**
     * Returns the key of the current element.
     *
     * @see https://www.php.net/manual/en/iterator.key.php
     *
     * @internal This method should not be called directly.
     */
    public function key(): mixed
    {
        return $this->resultset_key;
    }
    /**
     * Rewinds back to the first element of the Iterator.
     *
     * This is the first method called when starting a foreach loop. It will not
     * be executed after foreach loops.
     *
     * @see https://www.php.net/manual/en/iterator.rewind.php
     *
     * @internal This method should not be called directly.
     */
    public function rewind(): void
    {
        // Nothing to do: our DatabaseStatement can't be rewound. Error out when
        // attempted.
        if ($this->resultset_key >= 0) {
            $this->mark_resultset_iterable(false);
            throw new Database_Exception_Wrapper('Attempted rewinding a StatementInterface object when fetching has already started. Refactor your code to avoid rewinding statement objects.');
        }
    }
    /**
     * Moves the current position to the next element.
     *
     * This method is called after each foreach loop.
     *
     * @see https://www.php.net/manual/en/iterator.next.php
     *
     * @internal This method should not be called directly.
     */
    public function next(): void
    {
        $this->fetch();
    }
    /**
     * Checks if current position is valid.
     *
     * This method is called after ::rewind() and ::next() to check if the
     * current position is valid.
     *
     * @see https://www.php.net/manual/en/iterator.valid.php
     *
     * @internal This method should not be called directly.
     */
    public function valid(): bool
    {
        if ($this->is_resultset_iterable && $this->resultset_key === -1) {
            $this->fetch();
        }
        return $this->is_resultset_iterable;
    }
}