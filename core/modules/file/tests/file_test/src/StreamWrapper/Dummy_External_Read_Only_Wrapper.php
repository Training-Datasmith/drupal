<?php

declare(strict_types=1);

namespace Drupal\file_test\StreamWrapper;

use Drupal\Core\StreamWrapper\ReadOnlyStream;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;

/**
 * Helper class for testing the stream wrapper registry.
 *
 * Dummy external stream wrapper implementation (dummy-external-readonly://).
 */
class DummyExternalReadOnlyWrapper extends ReadOnlyStream
{
    /**
     * {@inheritdoc}
     */
    public static function getType()
    {
        return StreamWrapperInterface::READ_VISIBLE;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'Dummy external stream wrapper (readonly)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return 'Dummy external read-only stream wrapper for testing.';
    }

    /**
     * {@inheritdoc}
     */
    public function getExternalUrl()
    {
        [, $target] = explode('://', $this->uri, 2);
        return 'https://www.dummy-external-readonly.com/' . $target;
    }

    /**
     * {@inheritdoc}
     */
    public function realpath()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function dirname($uri = null)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function dir_closedir()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function dir_opendir($path, $options)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function dir_readdir()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function dir_rewinddir()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_cast($cast_as)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_close()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_eof()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_read($count)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_set_option($option, $arg1, $arg2)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_stat()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_tell()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function url_stat($path, $flags)
    {
        return false;
    }

}
