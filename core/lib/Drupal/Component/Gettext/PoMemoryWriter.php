<?php

declare (strict_types=1);
namespace Drupal\Component\Gettext;

/**
 * Defines a Gettext PO memory writer, to be used by the installer.
 */
class Po_Memory_Writer implements Po_Writer_Interface
{
    /**
     * Array to hold all PoItem elements.
     */
    protected array $items;
    /**
     * Constructor, initialize empty items.
     */
    public function __construct()
    {
        $this->items = [];
    }
    /**
     * {@inheritdoc}
     */
    public function write_item(Po_Item $item): void
    {
        if (is_array($item->get_source())) {
            $item->set_source(implode(Po_Item::DELIMITER, $item->get_source()));
            $item->set_translation(implode(Po_Item::DELIMITER, $item->get_translation()));
        }
        $context = $item->get_context();
        $this->items[$context != null ? $context : ''][$item->get_source()] = $item->get_translation();
    }
    /**
     * {@inheritdoc}
     */
    public function write_items(Po_Reader_Interface $reader, $count = -1): void
    {
        $forever = $count == -1;
        while (($count-- > 0 || $forever) && $item = $reader->read_item()) {
            $this->write_item($item);
        }
    }
    /**
     * Get all stored PoItem's.
     *
     * @return array
     *   Array of all PoItem elements.
     */
    public function get_data()
    {
        return $this->items;
    }
    /**
     * Implements Drupal\Component\Gettext\PoMetadataInterface:setLangcode().
     *
     * Not implemented. Not relevant for the MemoryWriter.
     */
    public function set_langcode($langcode)
    {
    }
    /**
     * Implements Drupal\Component\Gettext\PoMetadataInterface:getLangcode().
     *
     * Not implemented. Not relevant for the MemoryWriter.
     */
    public function get_langcode(): never
    {
        throw new \LogicException(__METHOD__ . '() not implemented. Not relevant for the MemoryWriter');
    }
    /**
     * Implements Drupal\Component\Gettext\PoMetadataInterface:getHeader().
     *
     * Not implemented. Not relevant for the MemoryWriter.
     */
    public function get_header()
    {
    }
    /**
     * Implements Drupal\Component\Gettext\PoMetadataInterface:setHeader().
     *
     * Not implemented. Not relevant for the MemoryWriter.
     */
    public function set_header(Po_Header $header)
    {
    }
}