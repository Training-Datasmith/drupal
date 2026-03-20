<?php

declare (strict_types=1);
namespace Drupal\Component\Annotation;

/**
 * Defines a Plugin annotation object that just contains an ID.
 *
 * @Annotation
 */
class Plugin_Id extends Annotation_Base
{
    /**
     * The plugin ID.
     *
     * When an annotation is given no key, 'value' is assumed by Doctrine.
     *
     * @var string
     */
    public $value;
    /**
     * {@inheritdoc}
     */
    public function get(): array
    {
        return ['id' => $this->value, 'class' => $this->class, 'provider' => $this->provider];
    }
    /**
     * {@inheritdoc}
     */
    public function get_id()
    {
        return $this->value;
    }
}