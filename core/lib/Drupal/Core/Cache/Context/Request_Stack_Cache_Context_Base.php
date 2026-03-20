<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Symfony\Component\Http_Foundation\Request_Stack;
/**
 * Defines a base class for cache contexts depending only on the request stack.
 *
 * Subclasses need to implement either
 * \Drupal\Core\Cache\Context\CacheContextInterface or
 * \Drupal\Core\Cache\Context\CalculatedCacheContextInterface.
 */
abstract class Request_Stack_Cache_Context_Base
{
    /**
     * The request stack.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $request_stack;
    /**
     * Constructs a new RequestStackCacheContextBase class.
     *
     * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
     *   The request stack.
     */
    public function __construct(Request_Stack $request_stack)
    {
        $this->request_stack = $request_stack;
    }
}