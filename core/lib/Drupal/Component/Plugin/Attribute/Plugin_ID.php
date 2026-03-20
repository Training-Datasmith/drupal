<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Attribute;

/**
 * Defines a Plugin attribute object that just contains an ID.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Plugin_Id extends Attribute_Base
{
    /**
     * {@inheritdoc}
     */
    public function get(): array
    {
        return ['id' => $this->get_id(), 'class' => $this->get_class(), 'provider' => $this->get_provider()];
    }
}