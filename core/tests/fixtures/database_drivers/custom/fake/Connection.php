<?php

declare(strict_types=1);

namespace Drupal\Driver\Database\fake;

use Drupal\Core\Database\Connection as CoreConnection;
use Drupal\Core\Database\ExceptionHandler;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Database\Query\Delete;
use Drupal\Core\Database\Query\Insert;
use Drupal\Core\Database\Query\Merge;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\Query\Truncate;
use Drupal\Core\Database\Query\Update;
use Drupal\Core\Database\Query\Upsert;
use Drupal\Core\Database\StatementWrapper;
use Drupal\Core\Database\Transaction;

/**
 * A fake Connection class for testing purposes.
 */
class Connection extends CoreConnection
{
    /**
     * {@inheritdoc}
     */
    protected $statementClass = null;

    /**
     * {@inheritdoc}
     */
    protected $statementWrapperClass = StatementWrapper::class;

    /**
     * {@inheritdoc}
     */
    protected $identifierQuotes = ['"', '"'];

    /**
     * Public property so we can test driver loading mechanism.
     *
     * @var string
     * @see driver().
     */
    public $driver = 'fake';

    /**
     * {@inheritdoc}
     */
    public function queryRange($query, $from, $count, array $args = [], array $options = []): null
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function driver(): string
    {
        return $this->driver;
    }

    /**
     * {@inheritdoc}
     */
    public function databaseType(): string
    {
        return 'fake';
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabase($database)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function mapConditionOperator($operator): null
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function exceptionHandler(): ExceptionHandler
    {
        return new ExceptionHandler();
    }

    /**
     * {@inheritdoc}
     */
    public function select($table, $alias = null, array $options = []): Select
    {
        return new Select($this, $table, $alias, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function insert($table, array $options = []): Insert
    {
        return new Insert($this, $table, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function merge($table, array $options = []): Merge
    {
        return new Merge($this, $table, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function upsert($table, array $options = []): Upsert
    {
        return new Upsert($this, $table, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function update($table, array $options = []): Update
    {
        return new Update($this, $table, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($table, array $options = []): Delete
    {
        return new Delete($this, $table, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function truncate($table, array $options = []): Truncate
    {
        return new Truncate($this, $table, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function schema()
    {
        if (empty($this->schema)) {
            $this->schema = new Schema($this);
        }
        return $this->schema;
    }

    /**
     * {@inheritdoc}
     */
    public function condition($conjunction): Condition
    {
        return new Condition($conjunction, false);
    }

    /**
     * {@inheritdoc}
     */
    public function startTransaction($name = ''): Transaction
    {
        return new Transaction($this, $name);
    }

}
