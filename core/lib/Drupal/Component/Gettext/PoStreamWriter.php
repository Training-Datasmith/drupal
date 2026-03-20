<?php

declare (strict_types=1);
namespace Drupal\Component\Gettext;

/**
 * Defines a Gettext PO stream writer.
 */
class Po_Stream_Writer implements Po_Writer_Interface, Po_Stream_Interface
{
    /**
     * URI of the PO stream that is being written.
     *
     * @var string
     */
    protected $uri;
    /**
     * The Gettext PO header.
     *
     * @var \Drupal\Component\Gettext\PoHeader
     */
    protected $header;
    /**
     * File handle of the current PO stream.
     *
     * @var resource
     */
    protected $fd;
    /**
     * The language code of this writer.
     *
     * @var string
     */
    protected $langcode;
    /**
     * Gets the PO header of the current stream.
     *
     * @return \Drupal\Component\Gettext\PoHeader
     *   The Gettext PO header.
     */
    public function get_header()
    {
        return $this->header;
    }
    /**
     * Set the PO header for the current stream.
     *
     * @param \Drupal\Component\Gettext\PoHeader $header
     *   The Gettext PO header to set.
     */
    public function set_header(Po_Header $header): void
    {
        $this->header = $header;
    }
    /**
     * Gets the current language code used.
     *
     * @return string
     *   The language code.
     */
    public function get_langcode()
    {
        return $this->langcode;
    }
    /**
     * Set the language code.
     *
     * @param string $langcode
     *   The language code.
     */
    public function set_langcode($langcode): void
    {
        $this->langcode = $langcode;
    }
    /**
     * {@inheritdoc}
     */
    public function open(): void
    {
        // Open in write mode. Will overwrite the stream if it already exists.
        $this->fd = fopen($this->get_uri(), 'w');
        // Write the header at the start.
        $this->write_header();
    }
    /**
     * Implements Drupal\Component\Gettext\PoStreamInterface::close().
     *
     * @throws \Exception
     *   If the stream is not open.
     */
    public function close(): void
    {
        if ($this->fd) {
            fclose($this->fd);
        } else {
            throw new \Exception('Cannot close stream that is not open.');
        }
    }
    /**
     * Write data to the stream.
     *
     * @param string $data
     *   Piece of string to write to the stream. If the value is not directly a
     *   string, casting will happen in writing.
     *
     * @throws \Exception
     *   If writing the data is not possible.
     */
    private function write($data): void
    {
        $result = fwrite($this->fd, $data);
        if ($result === false || $result != strlen($data)) {
            throw new \Exception('Unable to write data: ' . substr($data, 0, 20));
        }
    }
    /**
     * Write the PO header to the stream.
     */
    private function write_header(): void
    {
        $this->write($this->header);
    }
    /**
     * {@inheritdoc}
     */
    public function write_item(Po_Item $item): void
    {
        $this->write($item);
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
     * Implements Drupal\Component\Gettext\PoStreamInterface::getURI().
     *
     * @throws \Exception
     *   If the URI is not set.
     */
    public function get_uri()
    {
        if (empty($this->uri)) {
            throw new \Exception('No URI set.');
        }
        return $this->uri;
    }
    /**
     * {@inheritdoc}
     */
    public function set_uri($uri): void
    {
        $this->uri = $uri;
    }
}