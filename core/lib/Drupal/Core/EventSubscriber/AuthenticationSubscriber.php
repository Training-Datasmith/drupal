<?php

declare(strict_types=1);

namespace Drupal\Core\EventSubscriber;

use Drupal\Core\Authentication\AuthenticationProviderChallengeInterface;
use Drupal\Core\Authentication\AuthenticationProviderFilterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Authentication subscriber.
 *
 * Trigger authentication during the request.
 */
class AuthenticationSubscriber implements EventSubscriberInterface
{
    /**
     * Authentication provider filter.
     *
     * @var \Drupal\Core\Authentication\AuthenticationProviderFilterInterface|null
     */
    protected (\Drupal\Core\Authentication\AuthenticationProviderFilterInterface&\Drupal\Core\Authentication\AuthenticationProviderInterface)|null $filter;

    /**
     * Authentication challenge provider.
     *
     * @var \Drupal\Core\Authentication\AuthenticationProviderChallengeInterface|null
     */
    protected (\Drupal\Core\Authentication\AuthenticationProviderChallengeInterface&\Drupal\Core\Authentication\AuthenticationProviderInterface)|null $challengeProvider;

    /**
     * Constructs an authentication subscriber.
     *
     * @param \Drupal\Core\Authentication\AuthenticationProviderInterface $authenticationProvider
     *   An authentication provider.
     * @param \Drupal\Core\Session\AccountProxyInterface $accountProxy
     *   Account proxy.
     */
    public function __construct(protected \Drupal\Core\Authentication\AuthenticationProviderInterface $authenticationProvider, protected \Drupal\Core\Session\AccountProxyInterface $accountProxy)
    {
        $this->filter = ($this->authenticationProvider instanceof AuthenticationProviderFilterInterface) ? $this->authenticationProvider : null;
        $this->challengeProvider = ($this->authenticationProvider instanceof AuthenticationProviderChallengeInterface) ? $this->authenticationProvider : null;
    }

    /**
     * Authenticates user on request.
     *
     * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
     *   The request event.
     *
     * @see \Drupal\Core\Authentication\AuthenticationProviderInterface::authenticate()
     */
    public function onKernelRequestAuthenticate(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $request = $event->getRequest();
            if ($this->authenticationProvider->applies($request)) {
                $account = $this->authenticationProvider->authenticate($request);
                if ($account) {
                    $this->accountProxy->setAccount($account);
                    return;
                }
            }
        }
    }

    /**
     * Denies access if authentication provider is not allowed on this route.
     *
     * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
     *   The request event.
     */
    public function onKernelRequestFilterProvider(RequestEvent $event): void
    {
        if (isset($this->filter) && $event->isMainRequest()) {
            $request = $event->getRequest();
            if ($this->authenticationProvider->applies($request) && !$this->filter->appliesToRoutedRequest($request, true)) {
                throw new AccessDeniedHttpException('The used authentication method is not allowed on this route.');
            }
        }
    }

    /**
     * Respond with a challenge on access denied exceptions if appropriate.
     *
     * On a 403 (access denied), if there are no credentials on the request, some
     * authentication methods (e.g. basic auth) require that a challenge is sent
     * to the client.
     *
     * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
     *   The exception event.
     */
    public function onExceptionSendChallenge(ExceptionEvent $event): void
    {
        if (isset($this->challengeProvider) && $event->isMainRequest()) {
            $request = $event->getRequest();
            $exception = $event->getThrowable();
            if ($exception instanceof AccessDeniedHttpException && !$this->authenticationProvider->applies($request) && (!isset($this->filter) || $this->filter->appliesToRoutedRequest($request, false))) {
                $challenge_exception = $this->challengeProvider->challengeException($request, $exception);
                if ($challenge_exception) {
                    $event->setThrowable($challenge_exception);
                }
            }
        }
    }

    /**
     * Detect disallowed authentication methods on access denied exceptions.
     *
     * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
     *   The event.
     */
    public function onExceptionAccessDenied(ExceptionEvent $event): void
    {
        if (isset($this->filter) && $event->isMainRequest()) {
            $request = $event->getRequest();
            $exception = $event->getThrowable();
            if ($exception instanceof AccessDeniedHttpException && $this->authenticationProvider->applies($request) && !$this->filter->appliesToRoutedRequest($request, true)) {
                $event->setThrowable(new AccessDeniedHttpException('The used authentication method is not allowed on this route.', $exception));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        // The priority for authentication must be higher than the highest event
        // subscriber accessing the current user. Especially it must be higher than
        // LanguageRequestSubscriber as LanguageManager accesses the current user if
        // the language module is enabled.
        $events[KernelEvents::REQUEST][] = ['onKernelRequestAuthenticate', 300];

        // Access check must be performed after routing.
        $events[KernelEvents::REQUEST][] = ['onKernelRequestFilterProvider', 31];
        $events[KernelEvents::EXCEPTION][] = ['onExceptionSendChallenge', 75];
        $events[KernelEvents::EXCEPTION][] = ['onExceptionAccessDenied', 80];
        return $events;
    }

}
