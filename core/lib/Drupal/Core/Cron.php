<?php

declare (strict_types=1);
namespace Drupal\Core;

use Drupal\Component\Datetime\Time_Interface;
use Drupal\Component\Utility\Environment;
use Drupal\Component\Utility\Timer;
use Drupal\Core\Extension\Module_Handler_Interface;
use Drupal\Core\Lock\Lock_Backend_Interface;
use Drupal\Core\Queue\Delayable_Queue_Interface;
use Drupal\Core\Queue\Delayed_Requeue_Exception;
use Drupal\Core\Queue\Queue_Factory;
use Drupal\Core\Queue\Queue_Interface;
use Drupal\Core\Queue\Queue_Worker_Interface;
use Drupal\Core\Queue\Queue_Worker_Manager_Interface;
use Drupal\Core\Queue\Requeue_Exception;
use Drupal\Core\Queue\Suspend_Queue_Exception;
use Drupal\Core\Session\Account_Switcher_Interface;
use Drupal\Core\Session\Anonymous_User_Session;
use Drupal\Core\State\State_Interface;
use Drupal\Core\Utility\Error;
use Psr\Log\Logger_Interface;
use Psr\Log\Null_Logger;
/**
 * The Drupal core Cron service.
 */
class Cron implements Cron_Interface
{
    /**
     * The queue config.
     */
    protected array $queue_config;
    /**
     * Constructs a cron object.
     *
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
     *   The module handler.
     * @param \Drupal\Core\Lock\LockBackendInterface $lock
     *   The lock service.
     * @param \Drupal\Core\Queue\QueueFactory $queueFactory
     *   The queue service.
     * @param \Drupal\Core\State\StateInterface $state
     *   The state service.
     * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
     *   The account switching service.
     * @param \Psr\Log\LoggerInterface $logger
     *   A logger instance.
     * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueManager
     *   The queue plugin manager.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     * @param array $queue_config
     *   Queue configuration from the service container.
     */
    public function __construct(protected Module_Handler_Interface $module_handler, protected Lock_Backend_Interface $lock, protected Queue_Factory $queue_factory, protected State_Interface $state, protected Account_Switcher_Interface $account_switcher, protected Logger_Interface $logger, protected Queue_Worker_Manager_Interface $queue_manager, protected Time_Interface $time, array $queue_config)
    {
        $this->queue_config = $queue_config + ['suspendMaximumWait' => 30.0];
    }
    /**
     * {@inheritdoc}
     */
    public function run()
    {
        // Allow execution to continue even if the request gets cancelled.
        @ignore_user_abort(true);
        // Force the current user to anonymous to ensure consistent permissions on
        // cron runs.
        $this->account_switcher->switch_to(new Anonymous_User_Session());
        // Try to allocate enough time to run all the hook_cron implementations.
        Environment::set_time_limit(240);
        $return = false;
        // Try to acquire cron lock.
        if (!$this->lock->acquire('cron', 900.0)) {
            // Cron is still running normally.
            $this->logger->warning('Attempting to re-run cron while it is already running.');
        } else {
            $this->invoke_cron_handlers();
            // Process cron queues.
            $this->process_queues();
            $this->set_cron_last_time();
            // Release cron lock.
            $this->lock->release('cron');
            // Add watchdog message.
            $this->logger->info('Cron run completed.');
            // Return TRUE so other functions can check if it did run successfully.
            $return = true;
        }
        // Restore the user.
        $this->account_switcher->switch_back();
        return $return;
    }
    /**
     * Records and logs the request time for this cron invocation.
     */
    protected function set_cron_last_time()
    {
        // Record cron time.
        $request_time = $this->time->get_request_time();
        $this->state->set('system.cron_last', $request_time);
    }
    /**
     * Processes cron queues.
     */
    protected function process_queues()
    {
        $max_wait = (float) $this->queue_config['suspendMaximumWait'];
        // Build a stack of queues to work on.
        /** @var array<array{process_from: int<0, max>, queue: \Drupal\Core\Queue\QueueInterface, worker: \Drupal\Core\Queue\QueueWorkerInterface}> $queues */
        $queues = [];
        foreach ($this->queue_manager->get_definitions() as $queue_name => $queue_info) {
            if (!isset($queue_info['cron'])) {
                continue;
            }
            $queue = $this->queue_factory->get($queue_name);
            // Make sure every queue exists. There is no harm in trying to recreate
            // an existing queue.
            $queue->create_queue();
            $worker = $this->queue_manager->create_instance($queue_name);
            $queues[] = [
                // Set process_from to zero so each queue is always processed
                // immediately for the first time. This process_from timestamp will
                // change if a queue throws a delayable SuspendQueueException.
                'process_from' => 0,
                'queue' => $queue,
                'worker' => $worker,
            ];
        }
        // Work through stack of queues, re-adding to the stack when a delay is
        // necessary.
        while ($item = array_shift($queues)) {
            ['queue' => $queue, 'worker' => $worker, 'process_from' => $process_from] = $item;
            // Each queue will be processed immediately when it is reached for the
            // first time, as zero > currentTime will never be true.
            if ($process_from > $this->time->get_current_micro_time()) {
                $this->usleep((int) round($process_from - $this->time->get_current_micro_time(), 3) * 1000000);
            }
            try {
                $this->process_queue($queue, $worker);
            } catch (Suspend_Queue_Exception $e) {
                // Return to this queue after processing other queues if the delay is
                // within the threshold.
                if ($e->is_delayable() && $e->get_delay() < $max_wait) {
                    $item['process_from'] = $this->time->get_current_micro_time() + $e->get_delay();
                    // Place this queue back in the stack for processing later.
                    array_push($queues, $item);
                }
            }
            // Reorder the queue by next 'process_from' timestamp.
            usort($queues, fn(array $queue_a, array $queue_b) => $queue_a['process_from'] <=> $queue_b['process_from']);
        }
    }
    /**
     * Processes a cron queue.
     *
     * @param \Drupal\Core\Queue\QueueInterface $queue
     *   The queue.
     * @param \Drupal\Core\Queue\QueueWorkerInterface $worker
     *   The queue worker.
     *
     * @throws \Drupal\Core\Queue\SuspendQueueException
     *   If the queue was suspended.
     */
    protected function process_queue(Queue_Interface $queue, Queue_Worker_Interface $worker)
    {
        $lease_time = $worker->get_plugin_definition()['cron']['time'];
        $end = $this->time->get_current_time() + $lease_time;
        while ($this->time->get_current_time() < $end && $item = $queue->claim_item($lease_time)) {
            try {
                $worker->process_item($item->data);
                $queue->delete_item($item);
            } catch (Delayed_Requeue_Exception $e) {
                // The worker requested the task not be immediately re-queued.
                // - If the queue doesn't support ::delayItem(), we should leave the
                // item's current expiry time alone.
                // - If the queue does support ::delayItem(), we should allow the
                // queue to update the item's expiry using the requested delay.
                if ($queue instanceof Delayable_Queue_Interface) {
                    // This queue can handle a custom delay; use the duration provided
                    // by the exception.
                    $queue->delay_item($item, $e->get_delay());
                }
            } catch (Requeue_Exception) {
                // The worker requested the task be immediately requeued.
                $queue->release_item($item);
            } catch (Suspend_Queue_Exception $e) {
                // If the worker indicates the whole queue should be skipped, release
                // the item and go to the next queue.
                $queue->release_item($item);
                $this->logger->debug('A worker for @queue queue suspended further processing of the queue.', ['@queue' => $worker->get_plugin_id()]);
                // Skip to the next queue.
                throw $e;
            } catch (\Exception $e) {
                // In case of any other kind of exception, log it and leave the item
                // in the queue to be processed again later.
                Error::log_exception($this->logger, $e);
            }
        }
    }
    /**
     * Invokes any cron handlers implementing hook_cron.
     */
    protected function invoke_cron_handlers()
    {
        $module_previous = '';
        // If detailed logging isn't enabled, don't log individual execution times.
        $time_logging_enabled = \Drupal::config('system.cron')->get('logging');
        $logger = $time_logging_enabled ? $this->logger : new Null_Logger();
        // Iterate through the modules calling their cron handlers (if any):
        $this->module_handler->invoke_all_with('cron', function (callable $hook, string $module) use (&$module_previous, $logger): void {
            if (!$module_previous) {
                $logger->info('Starting execution of @module_cron().', ['@module' => $module]);
            } else {
                $logger->info('Starting execution of @module_cron(), execution of @module_previous_cron() took @time.', ['@module' => $module, '@module_previous' => $module_previous, '@time' => Timer::read('cron_' . $module_previous) . 'ms']);
            }
            Timer::start('cron_' . $module);
            // Do not let an exception thrown by one module disturb another.
            try {
                $hook();
            } catch (\Exception $e) {
                Error::log_exception($this->logger, $e);
            }
            Timer::stop('cron_' . $module);
            $module_previous = $module;
        });
        if ($module_previous) {
            $logger->info('Execution of @module_previous_cron() took @time.', ['@module_previous' => $module_previous, '@time' => Timer::read('cron_' . $module_previous) . 'ms']);
        }
    }
    /**
     * Delay execution in microseconds.
     *
     * @param int $microseconds
     *   Halt time in microseconds.
     */
    protected function usleep(int $microseconds): void
    {
        usleep($microseconds);
    }
}