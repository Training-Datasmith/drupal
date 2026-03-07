<?php

declare(strict_types=1);

namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the SessionExistsCacheContext service, for "session or not" caching.
 *
 * Cache context ID: 'session.exists'.
 */
class SessionExistsCacheContext implements CacheContextInterface
{
    /**
     * The request stack.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $requestStack;

    /**
     * Constructs a new SessionExistsCacheContext class.
     *
     * @param \Drupal\Core\Session\SessionConfigurationInterface $sessionConfiguration
     *   The session configuration.
     * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
     *   The request stack.
     */
    public function __construct(protected \Drupal\Core\Session\SessionConfigurationInterface $sessionConfiguration, RequestStack $request_stack)
    {
        $this->requestStack = $request_stack;
    }

    /**
     * {@inheritdoc}
     */
    public static function getLabel()
    {
        return t('Session exists');
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): string
    {
        return $this->sessionConfiguration->hasSession($this->requestStack->getCurrentRequest()) ? '1' : '0';
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheableMetadata(): \Drupal\Core\Cache\CacheableMetadata
    {
        return new CacheableMetadata();
    }

}
