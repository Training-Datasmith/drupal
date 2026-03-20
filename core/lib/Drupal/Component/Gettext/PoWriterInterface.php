<?php

declare (strict_types=1);
namespace Drupal\Component\Gettext;

/**
 * Shared interface definition for all Gettext PO Writers.
 */
interface Po_Writer_Interface extends Po_Metadata_Interface
{
    /**
     * Writes the given item.
     *
     * @param PoItem $item
     *   One specific item to write.
     */
    public function write_item(Po_Item $item);
    /**
     * Writes all or the given amount of items.
     *
     * @param PoReaderInterface $reader
     *   Reader to read PoItems from.
     * @param int $count
     *   Amount of items to read from $reader to write. If -1, all items are
     *   read from $reader.
     */
    public function write_items(Po_Reader_Interface $reader, $count = -1);
}