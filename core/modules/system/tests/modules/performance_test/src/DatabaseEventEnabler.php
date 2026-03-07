<?php

declare(strict_types=1);

namespace Drupal\performance_test;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Event\StatementEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Enables database event logging for the main request.
 */
class DatabaseEventEnabler implements HttpKernelInterface
{
    public function __construct(protected readonly HttpKernelInterface $httpKernel, protected readonly Connection $connection)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = true): Response
    {
        if ($type === static::MAIN_REQUEST) {
            $this->connection->enableEvents(StatementEvent::all());
        }
        return $this->httpKernel->handle($request, $type, $catch);
    }

}
