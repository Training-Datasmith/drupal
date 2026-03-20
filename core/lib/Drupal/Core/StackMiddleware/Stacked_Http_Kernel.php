<?php

declare(strict_types=1);

namespace Drupal\Core\StackMiddleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * Provides a stacked HTTP kernel.
 *
 * Copied from https://github.com/stackphp/builder/ with added compatibility
 * for Symfony 6.
 *
 * @see \Drupal\Core\DependencyInjection\Compiler\StackedKernelPass
 */
class StackedHttpKernel implements HttpKernelInterface, TerminableInterface
{
    /**
     * The decorated kernel.
     *
     * @var \Symfony\Component\HttpKernel\HttpKernelInterface
     */
    private $httpKernel;

    /**
     * A set of middlewares that are wrapped around this kernel.
     *
     * @var iterable<\Symfony\Component\HttpKernel\HttpKernelInterface>
     */
    private readonly iterable $middlewares;

    /**
     * Constructs a stacked HTTP kernel.
     *
     * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
     *   The decorated kernel.
     * @param iterable<\Symfony\Component\HttpKernel\HttpKernelInterface> $middlewares
     *   An array of previous middleware services.
     */
    public function __construct(HttpKernelInterface $http_kernel, iterable $middlewares)
    {
        if (is_array($middlewares)) {
            throw new \TypeError('The middlewares argument must be a lazy iterator, ' . gettype($middlewares) . ' given.');
        }
        $this->httpKernel = $http_kernel;
        $this->middlewares = $middlewares;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = HttpKernelInterface::MAIN_REQUEST, $catch = true): Response
    {
        return $this->httpKernel->handle($request, $type, $catch);
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(Request $request, Response $response): void
    {
        $previous = null;
        foreach ($this->middlewares as $kernel) {
            // If the previous kernel was terminable we can assume this middleware
            // has already been called.
            if (!$previous instanceof TerminableInterface && $kernel instanceof TerminableInterface) {
                $kernel->terminate($request, $response);
            }
            $previous = $kernel;
        }
    }

}
