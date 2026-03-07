<?php

declare(strict_types=1);

namespace Drupal\migrate\Plugin;

/**
 * Provides functionality for migration derivers.
 */
trait MigrationDeriverTrait
{
    /**
     * Returns a fully initialized instance of a source plugin.
     *
     * @param string $source_plugin_id
     *   The source plugin ID.
     *
     * @return \Drupal\migrate\Plugin\MigrateSourceInterface|\Drupal\migrate\Plugin\RequirementsInterface
     *   The fully initialized source plugin.
     */
    public static function getSourcePlugin($source_plugin_id)
    {
        $definition = [
          'source' => [
            'ignore_map' => true,
            'plugin' => $source_plugin_id,
          ],
          'destination' => [
            'plugin' => 'null',
          ],
          'idMap' => [
            'plugin' => 'null',
          ],
        ];
        return \Drupal::service('plugin.manager.migration')->createStubMigration($definition)->getSourcePlugin();
    }

}
