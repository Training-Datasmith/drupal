<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

/**
 * Utility trait to copy configuration from one storage to another.
 */
trait Storage_Copy_Trait
{
    /**
     * Copy the configuration from one storage to another and remove stale items.
     *
     * This method empties target storage and copies all collections from source.
     * Configuration is only copied and not imported, should not be used
     * with the active storage as the target.
     *
     * @param \Drupal\Core\Config\StorageInterface $source
     *   The configuration storage to copy from.
     * @param \Drupal\Core\Config\StorageInterface $target
     *   The configuration storage to copy to.
     */
    protected static function replace_storage_contents(Storage_Interface $source, Storage_Interface &$target)
    {
        // Remove all collections from the target which are not in the source.
        foreach (array_diff($target->get_all_collection_names(), $source->get_all_collection_names()) as $collection) {
            // We do this first so we don't have to loop over the added collections.
            $target->create_collection($collection)->delete_all();
        }
        // Copy all the configuration from all the collections.
        foreach (array_merge([Storage_Interface::DEFAULT_COLLECTION], $source->get_all_collection_names()) as $collection) {
            $source_collection = $source->create_collection($collection);
            $target_collection = $target->create_collection($collection);
            $names = $source_collection->list_all();
            // First we delete all the config which shouldn't be in the target.
            foreach (array_diff($target_collection->list_all(), $names) as $name) {
                $target_collection->delete($name);
            }
            // Then we loop over the config which needs to be there.
            foreach ($names as $name) {
                $data = $source_collection->read($name);
                if ($data !== false) {
                    if ($target_collection->read($name) !== $data) {
                        // Update the target collection if the data is different.
                        $target_collection->write($name, $data);
                    }
                } else {
                    $target_collection->delete($name);
                    \Drupal::logger('config')->notice('Missing required data for configuration: %config', ['%config' => $name]);
                }
            }
        }
        // Make sure that the target is set to the same collection as the source.
        $target = $target->create_collection($source->get_collection_name());
    }
}