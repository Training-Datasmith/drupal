<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
use Symfony\Component\Http_Foundation\Request_Stack;
/**
 * Defines the SessionExistsCacheContext service, for "session or not" caching.
 *
 * Cache context ID: 'session.exists'.
 */
class Session_Exists_Cache_Context implements Cache_Context_Interface
{
    /**
     * The request stack.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $request_stack;
    /**
     * Constructs a new SessionExistsCacheContext class.
     *
     * @param \Drupal\Core\Session\SessionConfigurationInterface $sessionConfiguration
     *   The session configuration.
     * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
     *   The request stack.
     */
    public function __construct(protected \Drupal\Core\Session\Session_Configuration_Interface $session_configuration, Request_Stack $request_stack)
    {
        $this->request_stack = $request_stack;
    }
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Session exists');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context(): string
    {
        return $this->session_configuration->has_session($this->request_stack->get_current_request()) ? '1' : '0';
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata(): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}