<?php

declare (strict_types=1);
namespace Drupal\Core\Default_Content;

use Drupal\Core\Serialization\Yaml;
/**
 * The result of exporting a content entity.
 *
 * @internal
 *   This API is experimental.
 */
final readonly class Export_Result implements \Stringable
{
    public function __construct(public array $data, public Export_Metadata $metadata)
    {
    }
    /**
     * Returns the exported entity data as YAML.
     *
     * @return string
     *   The exported entity data in YAML format.
     */
    public function __toString(): string
    {
        $data = ['_meta' => $this->metadata->get()] + $this->data;
        return (string) Yaml::encode($data);
    }
}