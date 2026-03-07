<?php

declare(strict_types=1);

namespace Drupal\Core\Http\Exception;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * A cacheable TooManyRequestsHttpException.
 */
class CacheableTooManyRequestsHttpException extends TooManyRequestsHttpException implements CacheableDependencyInterface
{
    use CacheableDependencyTrait;

    /**
     * {@inheritdoc}
     */
    public function __construct(CacheableDependencyInterface $cacheability, $retryAfter = null, $message = '', ?\Throwable $previous = null, $code = 0)
    {
        $this->setCacheability($cacheability);
        parent::__construct($retryAfter, $message, $previous, $code);
    }

}
