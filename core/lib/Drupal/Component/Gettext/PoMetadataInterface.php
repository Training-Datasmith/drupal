<?php

declare (strict_types=1);
namespace Drupal\Component\Gettext;

/**
 * Methods required for both reader and writer implementations.
 *
 * @see \Drupal\Component\Gettext\PoReaderInterface
 * @see \Drupal\Component\Gettext\PoWriterInterface
 */
interface Po_Metadata_Interface
{
    /**
     * Set language code.
     *
     * @param string $langcode
     *   Language code string.
     */
    public function set_langcode($langcode);
    /**
     * Get language code.
     *
     * @return string
     *   Language code string.
     */
    public function get_langcode();
    /**
     * Set header metadata.
     *
     * @param \Drupal\Component\Gettext\PoHeader $header
     *   Header object representing metadata in a PO header.
     */
    public function set_header(Po_Header $header);
    /**
     * Get header metadata.
     *
     * @return \Drupal\Component\Gettext\PoHeader
     *   Header instance representing metadata in a PO header.
     */
    public function get_header();
}