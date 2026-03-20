<?php

declare(strict_types=1);

namespace Drupal\image;

use Drupal\Core\Config\Entity\ConfigEntityStorage;

/**
 * Storage controller class for "image style" configuration entities.
 */
class ImageStyleStorage extends ConfigEntityStorage implements ImageStyleStorageInterface
{
    /**
     * Image style replacement memory storage.
     *
     * This value is not stored in the backend. It's used during the deletion of
     * an image style to save the replacement image style in the same request. The
     * value is used later, when resolving dependencies.
     *
     * @var string[]
     *
     * @see \Drupal\image\Form\ImageStyleDeleteForm::submitForm()
     */
    protected $replacement = [];

    /**
     * {@inheritdoc}
     */
    public function setReplacementId($name, $replacement): void
    {
        $this->replacement[$name] = $replacement;
    }

    /**
     * {@inheritdoc}
     */
    public function getReplacementId($name)
    {
        return $this->replacement[$name] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function clearReplacementId($name): void
    {
        unset($this->replacement[$name]);
    }

}
