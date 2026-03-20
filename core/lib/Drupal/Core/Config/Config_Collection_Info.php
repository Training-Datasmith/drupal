<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Component\Event_Dispatcher\Event;
/**
 * Gets information on all the possible configuration collections.
 */
class Config_Collection_Info extends Event
{
    /**
     * Configuration collection information keyed by collection name.
     *
     * The value is either the configuration factory override that is responsible
     * for the collection or null if there is not one.
     *
     * @var array
     */
    protected $collections = [];
    /**
     * Adds a collection to the list of possible collections.
     *
     * @param string $collection
     *   Collection name to add.
     * @param \Drupal\Core\Config\ConfigFactoryOverrideInterface $override_service
     *   (optional) The configuration factory override service responsible for the
     *   collection.
     *
     * @throws \InvalidArgumentException
     *   Exception thrown if $collection is equal to
     *   \Drupal\Core\Config\StorageInterface::DEFAULT_COLLECTION.
     */
    public function add_collection($collection, ?Config_Factory_Override_Interface $override_service = null): void
    {
        if ($collection == Storage_Interface::DEFAULT_COLLECTION) {
            throw new \InvalidArgumentException('Can not add the default collection to the ConfigCollectionInfo object');
        }
        $this->collections[$collection] = $override_service;
    }
    /**
     * Gets the list of possible collection names.
     *
     * @param bool $include_default
     *   (Optional) Include the default collection. Defaults to TRUE.
     *
     * @return array
     *   The list of possible collection names.
     */
    public function get_collection_names($include_default = true): array
    {
        $collection_names = array_keys($this->collections);
        sort($collection_names);
        if ($include_default) {
            array_unshift($collection_names, Storage_Interface::DEFAULT_COLLECTION);
        }
        return $collection_names;
    }
    /**
     * Gets the config factory override service responsible for the collection.
     *
     * @param string $collection
     *   The configuration collection.
     *
     * @return \Drupal\Core\Config\ConfigFactoryOverrideInterface|null
     *   The override service responsible for the collection if one exists. NULL
     *   if not.
     */
    public function get_override_service($collection)
    {
        return $this->collections[$collection] ?? null;
    }
}