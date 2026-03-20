<?php

declare (strict_types=1);
namespace Drupal\Component\Gettext;

/**
 * Common functions for file/stream based PO readers/writers.
 *
 * @see PoReaderInterface
 * @see PoWriterInterface
 */
interface Po_Stream_Interface
{
    /**
     * Open the stream. Set the URI for the stream earlier with setURI().
     */
    public function open();
    /**
     * Close the stream.
     */
    public function close();
    /**
     * Gets the URI of the PO stream that is being read or written.
     *
     * @return string
     *   URI string for this stream.
     */
    public function get_uri();
    /**
     * Set the URI of the PO stream that is going to be read or written.
     *
     * @param string $uri
     *   URI string to set for this stream.
     */
    public function set_uri($uri);
}