<?php

declare(strict_types=1);

namespace Drupal\file;

use Drupal\Core\TypedData\TypedData;

/**
 * Computed file URL property class.
 */
class ComputedFileUrl extends TypedData
{
    /**
     * Computed root-relative file URL.
     *
     * @var string
     */
    protected $url;

    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        if ($this->url !== null) {
            return $this->url;
        }

        assert($this->getParent()->getEntity() instanceof FileInterface);

        $uri = $this->getParent()->getEntity()->getFileUri();
        /** @var \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator */
        $file_url_generator = \Drupal::service('file_url_generator');
        $this->url = $file_url_generator->generateString($uri);

        return $this->url;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($value, $notify = true): void
    {
        $this->url = $value;

        // Notify the parent of any changes.
        if ($notify && isset($this->parent)) {
            $this->parent->onChange($this->name);
        }
    }

}
