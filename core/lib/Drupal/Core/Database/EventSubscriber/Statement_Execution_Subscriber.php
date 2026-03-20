<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Event_Subscriber;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Event\Statement_Execution_End_Event;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
/**
 * Response subscriber to statement executions.
 */
class Statement_Execution_Subscriber implements Event_Subscriber_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function get_subscribed_events(): array
    {
        return [Statement_Execution_End_Event::class => 'onStatementExecutionEnd'];
    }
    /**
     * Subscribes to a statement execution finished event.
     *
     * Logs the statement query if logging is active.
     *
     * @param \Drupal\Core\Database\Event\StatementExecutionEndEvent $event
     *   The database event.
     */
    public function on_statement_execution_end(Statement_Execution_End_Event $event): void
    {
        $logger = Database::get_connection($event->target, $event->key)->get_logger();
        if ($logger) {
            $logger->log_from_event($event);
        }
    }
}