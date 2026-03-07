<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Driver\CoreFake;

use Drupal\Driver\Database\fake\Connection as BaseConnection;

/**
 * A connection for testing database drivers.
 */
class Connection extends BaseConnection
{
    /**
     * {@inheritdoc}
     */
    public $driver = 'CoreFake';

}
