<?php

declare(strict_types=1);

namespace Drupal\migrate\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Logs values without changing them.
 *
 * The log plugin will log the values that are being processed by other plugins.
 *
 * Example:
 * @code
 * process:
 *   bar:
 *     plugin: log
 *     source: foo
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 */
#[MigrateProcess('log')]
class Log extends ProcessPluginBase
{
    /**
     * {@inheritdoc}
     */
    public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property)
    {
        $is_object = is_object($value);
        if (is_null($value) || is_bool($value)) {
            $export = var_export($value, true);
        } elseif (is_float($value)) {
            $export = sprintf('%f', $value);
        } elseif ($is_object && method_exists($value, 'toString')) {
            $export = print_r($value->toString(), true);
        } elseif ($is_object && method_exists($value, 'toArray')) {
            $export = print_r($value->toArray(), true);
        } elseif (is_string($value) || is_numeric($value) || is_array($value)) {
            $export = print_r($value, true);
        } elseif ($is_object && method_exists($value, '__toString')) {
            $export = print_r((string) $value, true);
        } else {
            $export = null;
        }

        $class_name = $export !== null && $is_object
          ? $class_name = $value::class . ":\n"
          : '';

        $message = $export === null
          ? "Unable to log the value for '$destination_property'"
          : "'$destination_property' value is $class_name'$export'";

        // Log the value.
        $migrate_executable->saveMessage($message);
        // Pass through the same value we received.
        return $value;
    }

}
