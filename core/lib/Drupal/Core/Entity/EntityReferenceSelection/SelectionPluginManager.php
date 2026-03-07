<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\EntityReferenceSelection;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin type manager for Entity Reference Selection plugins.
 *
 * @see \Drupal\Core\Entity\Annotation\EntityReferenceSelection
 * @see \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface
 * @see plugin_api
 */
class SelectionPluginManager extends DefaultPluginManager implements SelectionPluginManagerInterface, FallbackPluginManagerInterface
{
    /**
     * {@inheritdoc}
     */
    public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler)
    {
        $this->alterInfo('entity_reference_selection');
        $this->setCacheBackend($cache_backend, 'entity_reference_selection_plugins');

        parent::__construct('Plugin/EntityReferenceSelection', $namespaces, $module_handler, SelectionInterface::class, EntityReferenceSelection::class, \Drupal\Core\Entity\Annotation\EntityReferenceSelection::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getInstance(array $options)
    {
        if (!isset($options['target_type'])) {
            throw new \InvalidArgumentException("Missing required 'target_type' property for a EntityReferenceSelection plugin.");
        }

        // Initialize default options.
        $options += [
          'handler' => $this->getPluginId($options['target_type'], 'default'),
        ];

        // A specific selection plugin ID was already specified.
        if (str_contains((string) $options['handler'], ':')) {
            $plugin_id = $options['handler'];
        }
        // Only a selection group name was specified.
        else {
            $plugin_id = $this->getPluginId($options['target_type'], $options['handler']);
        }
        unset($options['handler']);

        return $this->createInstance($plugin_id, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getPluginId($target_type, $base_plugin_id): int|string|null
    {
        // Get all available selection plugins for this entity type.
        $selection_handler_groups = $this->getSelectionGroups($target_type);

        // Sort the selection plugins by weight and select the best match.
        uasort($selection_handler_groups[$base_plugin_id], \Drupal\Component\Utility\SortArray::sortByWeightElement(...));

        return array_key_last($selection_handler_groups[$base_plugin_id]);
    }

    /**
     * {@inheritdoc}
     * @return non-empty-array<mixed>[]
     */
    public function getSelectionGroups($entity_type_id): array
    {
        $plugins = [];
        $definitions = $this->getDefinitions();

        // Do not display the 'broken' plugin in the UI.
        unset($definitions['broken']);

        foreach ($definitions as $plugin_id => $plugin) {
            if (empty($plugin['entity_types']) || in_array($entity_type_id, $plugin['entity_types'])) {
                $plugins[$plugin['group']][$plugin_id] = $plugin;
            }
        }

        return $plugins;
    }

    /**
     * {@inheritdoc}
     */
    public function getSelectionHandler(FieldDefinitionInterface $field_definition, ?EntityInterface $entity = null)
    {
        $options = $field_definition->getSetting('handler_settings') ?: [];
        $options += [
          'target_type' => $field_definition->getFieldStorageDefinition()->getSetting('target_type'),
          'handler' => $field_definition->getSetting('handler'),
          'entity' => $entity,
        ];
        return $this->getInstance($options);
    }

    /**
     * {@inheritdoc}
     */
    public function getFallbackPluginId($plugin_id, array $configuration = []): string
    {
        return 'broken';
    }

}
