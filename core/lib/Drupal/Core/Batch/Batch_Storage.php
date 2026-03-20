<?php

declare (strict_types=1);
namespace Drupal\Core\Batch;

use Drupal\Component\Datetime\Time_Interface;
use Drupal\Core\Access\Csrf_Token_Generator;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database_Exception;
use Symfony\Component\Http_Foundation\Session\Session_Interface;
/**
 * Defines the storage handler class for batches.
 */
class Batch_Storage implements Batch_Storage_Interface
{
    /**
     * The table name.
     */
    public const TABLE_NAME = 'batch';
    /**
     * Constructs the database batch storage service.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   The database connection.
     * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
     *   The session.
     * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfToken
     *   The CSRF token generator.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(protected Connection $connection, protected Session_Interface $session, protected Csrf_Token_Generator $csrf_token, protected Time_Interface $time)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function load($id)
    {
        // Ensure that a session is started before using the CSRF token generator.
        $this->session->start();
        try {
            $batch = $this->connection->select('batch', 'b')->fields('b', ['batch'])->condition('bid', $id)->condition('token', $this->csrf_token->get($id))->execute()->fetch_field();
        } catch (\Exception $e) {
            $this->catch_exception($e);
            $batch = false;
        }
        if ($batch) {
            return unserialize($batch);
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function delete($id): void
    {
        try {
            $this->connection->delete('batch')->condition('bid', $id)->execute();
        } catch (\Exception $e) {
            $this->catch_exception($e);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function update(array $batch): void
    {
        try {
            $this->connection->update('batch')->fields(['batch' => serialize($batch)])->condition('bid', $batch['id'])->execute();
        } catch (\Exception $e) {
            $this->catch_exception($e);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function cleanup(): void
    {
        try {
            // Cleanup the batch table and the queue for failed batches.
            $this->connection->delete('batch')->condition('timestamp', $this->time->get_request_time() - 864000, '<')->execute();
        } catch (\Exception $e) {
            $this->catch_exception($e);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function create(array $batch): void
    {
        // Ensure that a session is started before using the CSRF token generator,
        // and update the database record.
        $this->session->start();
        $this->connection->update('batch')->fields(['token' => $this->csrf_token->get($batch['id']), 'batch' => serialize($batch)])->condition('bid', $batch['id'])->execute();
    }
    /**
     * Returns a new batch id.
     *
     * @return int
     *   A batch id.
     */
    public function get_id(): int
    {
        $try_again = false;
        try {
            // The batch table might not yet exist.
            return $this->do_insert_batch_record();
        } catch (\Exception $e) {
            // If there was an exception, try to create the table.
            if (!$try_again = $this->ensure_table_exists()) {
                // If the exception happened for other reason than the missing table,
                // propagate the exception.
                throw $e;
            }
        }
        // Now that the table has been created, try again if necessary.
        if ($try_again) {
            return $this->do_insert_batch_record();
        }
    }
    /**
     * Inserts a record in the table and returns the batch id.
     *
     * @return int
     *   A batch id.
     */
    protected function do_insert_batch_record(): int
    {
        return $this->connection->insert('batch')->fields(['timestamp' => $this->time->get_request_time(), 'token' => '', 'batch' => null])->execute();
    }
    /**
     * Check if the table exists and create it if not.
     */
    protected function ensure_table_exists(): bool
    {
        try {
            $database_schema = $this->connection->schema();
            $schema_definition = $this->schema_definition();
            $database_schema->create_table(static::TABLE_NAME, $schema_definition);
        } catch (Database_Exception) {
        } catch (\Exception) {
            return false;
        }
        return true;
    }
    /**
     * Act on an exception when batch might be stale.
     *
     * If the table does not yet exist, that's fine, but if the table exists and
     * yet the query failed, then the batch is stale and the exception needs to
     * propagate.
     *
     * @param \Exception $e
     *   The exception.
     *
     * @throws \Exception
     */
    protected function catch_exception(\Exception $e)
    {
        if ($this->connection->schema()->table_exists(static::TABLE_NAME)) {
            throw $e;
        }
    }
    /**
     * Defines the schema for the batch table.
     *
     * @internal
     */
    public function schema_definition(): array
    {
        return ['description' => 'Stores details about batches (processes that run in multiple HTTP requests).', 'fields' => ['bid' => ['description' => 'Primary Key: Unique batch ID.', 'type' => 'serial', 'unsigned' => true, 'not null' => true], 'token' => ['description' => "A string token generated against the current user's session id and the batch id, used to ensure that only the user who submitted the batch can effectively access it.", 'type' => 'varchar_ascii', 'length' => 64, 'not null' => true], 'timestamp' => ['description' => 'A Unix timestamp indicating when this batch was submitted for processing. Stale batches are purged at cron time.', 'type' => 'int', 'not null' => true], 'batch' => ['description' => 'A serialized array containing the processing data for the batch.', 'type' => 'blob', 'not null' => false, 'size' => 'big']], 'primary key' => ['bid'], 'indexes' => ['token' => ['token']]];
    }
}