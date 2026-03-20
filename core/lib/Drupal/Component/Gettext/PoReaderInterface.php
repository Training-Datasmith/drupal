<?php

declare (strict_types=1);
namespace Drupal\Component\Gettext;

/**
 * Shared interface definition for all Gettext PO Readers.
 */
interface Po_Reader_Interface extends Po_Metadata_Interface
{
    /**
     * Reads and returns a PoItem (source/translation pair).
     *
     * @return \Drupal\Component\Gettext\PoItem
     *   Wrapper for item data instance.
     */
    public function read_item();
}