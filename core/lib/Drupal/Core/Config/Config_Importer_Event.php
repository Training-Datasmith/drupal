<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Component\Event_Dispatcher\Event;
/**
 * Configuration event fired when importing a configuration object.
 */
class Config_Importer_Event extends Event
{
    /**
     * Constructs ConfigImporterEvent.
     *
     * @param \Drupal\Core\Config\ConfigImporter $configImporter
     *   A config import object to notify listeners about.
     */
    public function __construct(protected \Drupal\Core\Config\Config_Importer $config_importer)
    {
    }
    /**
     * Gets the config import object.
     *
     * @return \Drupal\Core\Config\ConfigImporter
     *   The ConfigImporter object.
     */
    public function get_config_importer()
    {
        return $this->config_importer;
    }
    /**
     * Gets the list of changes that will be imported.
     *
     * @param string $op
     *   (optional) A change operation. Either delete, create or update. If
     *   supplied the returned list will be limited to this operation.
     * @param string $collection
     *   (optional) The collection to get the changelist for. Defaults to the
     *   default collection.
     *
     * @return array
     *   An array of config changes that are yet to be imported.
     *
     * @see \Drupal\Core\Config\StorageComparerInterface::getChangelist()
     */
    public function get_changelist($op = null, $collection = Storage_Interface::DEFAULT_COLLECTION)
    {
        return $this->config_importer->get_storage_comparer()->get_changelist($op, $collection);
    }
}