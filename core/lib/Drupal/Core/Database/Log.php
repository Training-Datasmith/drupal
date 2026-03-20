<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

use Drupal\Core\Database\Event\Statement_Execution_End_Event;
/**
 * Database query logger.
 *
 * We log queries in a separate object rather than in the connection object
 * because we want to be able to see all queries sent to a given database, not
 * database target. If we logged the queries in each connection object we
 * would not be able to track what queries went to which target.
 *
 * Every connection has one and only one logging object on it for all targets
 * and logging keys.
 */
class Log
{
    /**
     * Cache of logged queries.
     *
     * This will only be used if the query logger is enabled.
     *
     * @var array
     * The structure for the logging array is as follows:
     *
     * @code
     * [
     *   $logging_key = [
     *     ['query' => '', 'args' => [], 'caller' => '', 'target' => '', 'time' => 0, 'start' => 0],
     *     ['query' => '', 'args' => [], 'caller' => '', 'target' => '', 'time' => 0, 'start' => 0],
     *   ],
     * ];
     * @endcode
     */
    protected $query_log = [];
    /**
     * Constructor.
     *
     * @param string $connectionKey
     *   The database connection key for which to enable logging.
     */
    public function __construct(
        /**
         * The connection key for which this object is logging.
         */
        protected $connection_key = 'default'
    )
    {
    }
    /**
     * Begin logging queries to the specified connection and logging key.
     *
     * If the specified logging key is already running this method does nothing.
     *
     * @param string $logging_key
     *   The identification key for this log request. By specifying different
     *   logging keys we are able to start and stop multiple logging runs
     *   simultaneously without them colliding.
     */
    public function start($logging_key): void
    {
        if (empty($this->query_log[$logging_key])) {
            $this->clear($logging_key);
        }
    }
    /**
     * Retrieve the query log for the specified logging key so far.
     *
     * @param string $logging_key
     *   The logging key to fetch.
     *
     * @return array
     *   An indexed array of all query records for this logging key.
     */
    public function get($logging_key)
    {
        return $this->query_log[$logging_key];
    }
    /**
     * Empty the query log for the specified logging key.
     *
     * This method does not stop logging, it simply clears the log. To stop
     * logging, use the end() method.
     *
     * @param string $logging_key
     *   The logging key to empty.
     */
    public function clear($logging_key): void
    {
        $this->query_log[$logging_key] = [];
    }
    /**
     * Stop logging for the specified logging key.
     *
     * @param string $logging_key
     *   The logging key to stop.
     */
    public function end($logging_key): void
    {
        unset($this->query_log[$logging_key]);
    }
    /**
     * Log a query to all active logging keys, from a statement execution event.
     *
     * @param \Drupal\Core\Database\Event\StatementExecutionEndEvent $event
     *   The statement execution event.
     */
    public function log_from_event(Statement_Execution_End_Event $event): void
    {
        foreach (array_keys($this->query_log) as $key) {
            $this->query_log[$key][] = ['query' => $event->query_string, 'args' => $event->args, 'target' => $event->target, 'caller' => $event->caller, 'time' => $event->get_elapsed_time(), 'start' => $event->start_time];
        }
    }
}