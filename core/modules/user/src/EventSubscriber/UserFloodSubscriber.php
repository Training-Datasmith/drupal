<?php

declare(strict_types=1);

namespace Drupal\user\EventSubscriber;

use Drupal\Core\Site\Settings;
use Drupal\user\Event\UserEvents;
use Drupal\user\Event\UserFloodEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Logs details of User Flood Control events.
 */
class UserFloodSubscriber implements EventSubscriberInterface
{
    /**
     * Constructs a UserFloodSubscriber.
     *
     * @param \Psr\Log\LoggerInterface $logger
     *   A logger instance.
     */
    public function __construct(protected ?\Psr\Log\LoggerInterface $logger = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        $events[UserEvents::FLOOD_BLOCKED_USER][] = ['blockedUser'];
        $events[UserEvents::FLOOD_BLOCKED_IP][] = ['blockedIp'];
        return $events;
    }

    /**
     * An attempt to login has been blocked based on user name.
     *
     * @param \Drupal\user\Event\UserFloodEvent $floodEvent
     *   The flood event.
     */
    public function blockedUser(UserFloodEvent $floodEvent): void
    {
        if (Settings::get('log_user_flood', true)) {
            $uid = $floodEvent->getUid();
            if ($floodEvent->hasIp()) {
                $ip = $floodEvent->getIp();
                $this->logger->notice('Flood control blocked login attempt for uid %uid from %ip', ['%uid' => $uid, '%ip' => $ip]);
                return;
            }
            $this->logger->notice('Flood control blocked login attempt for uid %uid', ['%uid' => $uid]);
        }
    }

    /**
     * An attempt to login has been blocked based on IP.
     *
     * @param \Drupal\user\Event\UserFloodEvent $floodEvent
     *   The flood event.
     */
    public function blockedIp(UserFloodEvent $floodEvent): void
    {
        if (Settings::get('log_user_flood', true)) {
            $this->logger->notice('Flood control blocked login attempt from %ip', ['%ip' => $floodEvent->getIp()]);
        }
    }

}
