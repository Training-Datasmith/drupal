<?php

declare(strict_types=1);

namespace Drupal\serialization\Normalizer;

use Drupal\Core\Serialization\Attribute\JsonSchema;

/**
 * Null normalizer.
 */
class NullNormalizer extends NormalizerBase
{
    use SchematicNormalizerTrait;

    /**
     * The interface or class that this Normalizer supports.
     *
     * @var string[]
     */
    protected array $supportedTypes = ['*' => false];

    /**
     * Constructs a NullNormalizer object.
     *
     * @param string|array $supported_interface_of_class
     *   The supported interface(s) or class(es).
     */
    public function __construct($supported_interface_of_class)
    {
        $this->supportedTypes = [$supported_interface_of_class => true];
    }

    /**
     * {@inheritdoc}
     */
    #[JsonSchema(['type' => 'null'])]
    public function doNormalize($object, $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function getNormalizationSchema(mixed $object, array $context = []): array
    {
        return ['type' => 'null'];
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTypes(?string $format): array
    {
        return $this->supportedTypes;
    }

}
