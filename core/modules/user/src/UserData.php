<?php

declare(strict_types=1);

namespace Drupal\user;

use Drupal\Core\Database\Connection;

/**
 * Defines the user data service.
 */
class UserData implements UserDataInterface
{
    /**
     * Constructs a new user data service.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   The database connection to use.
     */
    public function __construct(protected \Drupal\Core\Database\Connection $connection)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function get($module, $uid = null, $name = null)
    {
        $query = $this->connection->select('users_data', 'ud')
          ->fields('ud')
          ->condition('module', $module);
        if (isset($uid)) {
            $query->condition('uid', $uid);
        }
        if (isset($name)) {
            $query->condition('name', $name);
        }
        $result = $query->execute();
        // If $module, $uid, and $name were passed, return the value.
        if (isset($name) && isset($uid)) {
            $result = $result->fetchAllAssoc('uid');
            if (isset($result[$uid])) {
                return $result[$uid]->serialized ? unserialize($result[$uid]->value, ['allowed_classes' => false]) : $result[$uid]->value;
            }
            return null;
        }
        $return = [];
        // If $module and $uid were passed, return data keyed by name.
        if (isset($uid)) {
            foreach ($result as $record) {
                $return[$record->name] = ($record->serialized ? unserialize($record->value, ['allowed_classes' => false]) : $record->value);
            }
            return $return;
        }
        // If $module and $name were passed, return data keyed by uid.
        if (isset($name)) {
            foreach ($result as $record) {
                $return[$record->uid] = ($record->serialized ? unserialize($record->value, ['allowed_classes' => false]) : $record->value);
            }
            return $return;
        }
        // If only $module was passed, return data keyed by uid and name.
        foreach ($result as $record) {
            $return[$record->uid][$record->name] = ($record->serialized ? unserialize($record->value, ['allowed_classes' => false]) : $record->value);
        }
        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function set($module, $uid, $name, $value): void
    {
        $serialized = (int) !is_scalar($value);
        if ($serialized) {
            $value = serialize($value);
        }
        $this->connection->merge('users_data')
          ->keys([
            'uid' => $uid,
            'module' => $module,
            'name' => $name,
          ])
          ->fields([
            'value' => $value,
            'serialized' => $serialized,
          ])
          ->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function delete($module = null, $uid = null, $name = null): void
    {
        $query = $this->connection->delete('users_data');
        // Cast scalars to array so we can consistently use an IN condition.
        if (isset($module)) {
            $query->condition('module', (array) $module, 'IN');
        }
        if (isset($uid)) {
            $query->condition('uid', (array) $uid, 'IN');
        }
        if (isset($name)) {
            $query->condition('name', (array) $name, 'IN');
        }
        $query->execute();
    }

}
