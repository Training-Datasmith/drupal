<?php

declare (strict_types=1);
namespace Drupal\Core\Default_Content;

use Symfony\Contracts\Event_Dispatcher\Event;
/**
 * Event dispatched before an entity is created during default content import.
 *
 * This event is dispatched for each entity before it is created from the
 * decoded data. Subscribers can modify the entity data (default and
 * translations) but not the metadata.
 */
final class Pre_Entity_Import_Event extends Event
{
    /**
     * The entity metadata.
     *
     * @var array<string, mixed>
     */
    public readonly array $metadata;
    public function __construct(public array $data)
    {
        $this->metadata = $data['_meta'];
        unset($this->data['_meta']);
    }
}