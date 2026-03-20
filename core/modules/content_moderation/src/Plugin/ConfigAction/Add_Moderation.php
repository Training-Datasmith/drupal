<?php

declare(strict_types=1);

namespace Drupal\content_moderation\Plugin\ConfigAction;

use Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface;
use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\workflows\WorkflowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
    id: 'add_moderation',
    entity_types: ['workflow'],
    deriver: AddModerationDeriver::class,
)]
final readonly class AddModeration implements ConfigActionPluginInterface, ContainerFactoryPluginInterface
{
    public function __construct(
        private ConfigManagerInterface $configManager,
        private EntityTypeManagerInterface $entityTypeManager,
        private string $pluginId,
        private string $targetEntityType,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static
    {
        assert(is_array($plugin_definition));
        $target_entity_type = $plugin_definition['target_entity_type'];

        return new static(
            $container->get(ConfigManagerInterface::class),
            $container->get(EntityTypeManagerInterface::class),
            $plugin_id,
            $target_entity_type,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function apply(string $configName, mixed $value): void
    {
        $workflow = $this->configManager->loadConfigEntityByName($configName);
        assert($workflow instanceof WorkflowInterface);

        $plugin = $workflow->getTypePlugin();
        if (!$plugin instanceof ContentModerationInterface) {
            throw new ConfigActionException("The $this->pluginId config action only works with Content Moderation workflows.");
        }

        assert($value === '*' || is_array($value));
        if ($value === '*') {
            /** @var \Drupal\Core\Entity\EntityTypeInterface $definition */
            $definition = $this->entityTypeManager->getDefinition($this->targetEntityType);
            /** @var string $bundle_entity_type */
            $bundle_entity_type = $definition->getBundleEntityType();

            $value = $this->entityTypeManager->getStorage($bundle_entity_type)
              ->getQuery()
              ->accessCheck(false)
              ->execute();
        }
        foreach ($value as $bundle) {
            $plugin->addEntityTypeAndBundle($this->targetEntityType, $bundle);
        }
        $workflow->save();
    }

}
