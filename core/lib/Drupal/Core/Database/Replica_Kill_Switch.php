<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

use Drupal\Core\Site\Settings;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Foundation\Session\Session_Interface;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * Provides replica server kill switch to ignore it.
 */
class Replica_Kill_Switch implements Event_Subscriber_Interface
{
    /**
     * The session.
     *
     * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
     */
    protected $session;
    /**
     * Constructs a ReplicaKillSwitch object.
     *
     * @param \Drupal\Core\Site\Settings $settings
     *   The settings object.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
     *   The session.
     */
    public function __construct(protected \Drupal\Core\Site\Settings $settings, protected \Drupal\Component\Datetime\Time_Interface $time, Session_Interface $session)
    {
        $this->session = $session;
    }
    /**
     * Denies access to replica database on the current request.
     *
     * @see https://www.drupal.org/node/2286193
     */
    public function trigger(): void
    {
        $connection_info = Database::get_connection_info();
        // Only set ignore_replica_server if there are replica servers being used,
        // which is assumed if there are more than one.
        if (count($connection_info) > 1) {
            // Five minutes is long enough to allow the replica to break and resume
            // interrupted replication without causing problems on the Drupal site
            // from the old data.
            $duration = $this->settings->get('maximum_replication_lag', 300);
            // Set session variable with amount of time to delay before using replica.
            $this->session->set('ignore_replica_server', $this->time->get_request_time() + $duration);
        }
    }
    /**
     * Checks and disables the replica database server if appropriate.
     *
     * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
     *   The Event to process.
     */
    public function check_replica_server(Request_Event $event): void
    {
        // Ignore replica database servers for this request.
        //
        // In Drupal's distributed database structure, new data is written to the
        // master and then propagated to the replica servers.  This means there is a
        // lag between when data is written to the master and when it is available
        // on the replica. At these times, we will want to avoid using a replica
        // server temporarily. For example, if a user posts a new node then we want
        // to disable the replica server for that user temporarily to allow the
        // replica server to catch up.
        // That way, that user will see their changes immediately while for other
        // users we still get the benefits of having a replica server, just with
        // slightly stale data. Code that wants to disable the replica server should
        // use the 'database.replica_kill_switch' service's trigger() method to set
        // 'ignore_replica_server' session flag to the timestamp after which the
        // replica can be re-enabled.
        if ($this->session->has('ignore_replica_server')) {
            if ($this->session->get('ignore_replica_server') >= $this->time->get_request_time()) {
                Database::ignore_target('default', 'replica');
            } else {
                $this->session->remove('ignore_replica_server');
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public static function get_subscribed_events(): array
    {
        $events[Kernel_Events::REQUEST][] = ['checkReplicaServer'];
        return $events;
    }
}