<?php

declare(strict_types=1);

namespace Drupal\jsonapi\Normalizer;

use Drupal\jsonapi\ResourceType\ResourceType;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Converts the Drupal entity object to a JSON:API array structure.
 *
 * @internal JSON:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 * @see jsonapi.api.php
 */
abstract class EntityDenormalizerBase extends NormalizerBase implements DenormalizerInterface
{
    /**
     * The JSON:API resource type repository.
     *
     * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
     */
    protected $resourceTypeRepository;

    /**
     * Constructs an EntityDenormalizerBase object.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager.
     * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
     *   The entity field manager.
     * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $pluginManager
     *   The plugin manager for fields.
     */
    public function __construct(protected \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager, protected \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager, protected \Drupal\Core\Field\FieldTypePluginManagerInterface $pluginManager)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        throw new \LogicException('This method should never be called.');
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (empty($context['resource_type']) || !$context['resource_type'] instanceof ResourceType) {
            throw new PreconditionFailedHttpException('Missing context during denormalization.');
        }
        $resource_type = $context['resource_type'];
        $entity_type_id = $resource_type->getEntityTypeId();
        $bundle = $resource_type->getBundle();
        $bundle_key = $this->entityTypeManager->getDefinition($entity_type_id)
          ->getKey('bundle');
        if ($bundle_key && $bundle) {
            $data[$bundle_key] = $bundle;
        }

        return $this->entityTypeManager->getStorage($entity_type_id)
          ->create($this->prepareInput($data, $resource_type, $format, $context));
    }

    /**
     * Prepares the input data to create the entity.
     *
     * @param array $data
     *   The input data to modify.
     * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
     *   Contains the info about the resource type.
     * @param string $format
     *   Format the given data was extracted from.
     * @param array $context
     *   Options available to the denormalizer.
     *
     * @return array
     *   The modified input data.
     */
    abstract protected function prepareInput(array $data, ResourceType $resource_type, $format, array $context);

}
