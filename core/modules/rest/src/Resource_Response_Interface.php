<?php

declare(strict_types=1);

namespace Drupal\rest;

/**
 * Defines a common interface for resource responses.
 */
interface ResourceResponseInterface
{
    /**
     * Returns response data that should be serialized.
     *
     * @return mixed
     *   Response data that should be serialized.
     */
    public function getResponseData();

}
