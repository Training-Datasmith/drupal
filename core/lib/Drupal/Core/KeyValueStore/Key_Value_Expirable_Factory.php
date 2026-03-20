<?php

declare(strict_types=1);

namespace Drupal\Core\KeyValueStore;

/**
 * Defines the key/value store factory.
 */
class KeyValueExpirableFactory extends KeyValueFactory implements KeyValueExpirableFactoryInterface
{
    public const DEFAULT_SERVICE = 'keyvalue.expirable.database';

    public const SPECIFIC_PREFIX = 'keyvalue_expirable_service_';

    public const DEFAULT_SETTING = 'keyvalue_expirable_default';

}
