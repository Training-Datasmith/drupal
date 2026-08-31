<?php

declare(strict_types=1);

namespace Drupal\config_translation;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\locale\LocaleConfigManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Configuration mapper for configuration entities.
 */
class ConfigEntityMapper extends ConfigNamesMapper implements ConfigEntityMapperInterface
{
    /**
     * Configuration entity type name.
     *
     * @var string
     */
    protected $entityType;

    /**
     * Loaded entity instance to help produce the translation interface.
     *
     * @var \Drupal\Core\Config\Entity\ConfigEntityInterface
     */
    protected $entity;

    /**
     * The label for the entity type.
     *
     * @var string
     */
    protected $typeLabel;

    /**
     * Constructs a ConfigEntityMapper.
     *
     * @param string $plugin_id
     *   The config mapper plugin ID.
     * @param mixed $plugin_definition
     *   An array of plugin information as documented in
     *   ConfigNamesMapper::__construct() with the following additional keys:
     *   - entity_type: The name of the entity type this mapper belongs to.
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   The configuration factory.
     * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
     *   The typed configuration manager.
     * @param \Drupal\locale\LocaleConfigManager $locale_config_manager
     *   The locale configuration manager.
     * @param \Drupal\config_translation\ConfigMapperManagerInterface $config_mapper_manager
     *   The mapper plugin discovery service.
     * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
     *   The route provider.
     * @param \Drupal\Core\StringTranslation\TranslationInterface $translation_manager
     *   The string translation manager.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager.
     * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
     *   The language manager.
     * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher
     *   The event dispatcher.
     */
    public function __construct($plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config, LocaleConfigManager $locale_config_manager, ConfigMapperManagerInterface $config_mapper_manager, RouteProviderInterface $route_provider, TranslationInterface $translation_manager, protected \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $language_manager, ?EventDispatcherInterface $event_dispatcher = null)
    {
        parent::__construct($plugin_id, $plugin_definition, $config_factory, $typed_config, $locale_config_manager, $config_mapper_manager, $route_provider, $translation_manager, $language_manager, $event_dispatcher);
        $this->setType($plugin_definition['entity_type']);
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static
    {
        // Note that we ignore the plugin $configuration because mappers have
        // nothing to configure in themselves.
        return new static(
            $plugin_id,
            $plugin_definition,
            $container->get('config.factory'),
            $container->get('config.typed'),
            $container->get('locale.config_manager'),
            $container->get('plugin.manager.config_translation.mapper'),
            $container->get('router.route_provider'),
            $container->get('string_translation'),
            $container->get('entity_type.manager'),
            $container->get('language_manager'),
            $container->get('event_dispatcher')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function populateFromRouteMatch(RouteMatchInterface $route_match): void
    {
        $entity = $route_match->getParameter($this->entityType);
        $this->setEntity($entity);
        parent::populateFromRouteMatch($route_match);
    }

    /**
     * {@inheritdoc}
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * {@inheritdoc}
     */
    public function setEntity(ConfigEntityInterface $entity): bool
    {
        if (isset($this->entity)) {
            return false;
        }

        $this->entity = $entity;

        // Add the list of configuration IDs belonging to this entity. We add on a
        // possibly existing list of names. This allows modules to alter the entity
        // page with more names if form altering added more configuration to an
        // entity. This is not a Drupal 8 best practice (ideally the configuration
        // would have pluggable components), but this may happen as well.
        /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type_info */
        $entity_type_info = $this->entityTypeManager->getDefinition($this->entityType);
        $this->addConfigName($entity_type_info->getConfigPrefix() . '.' . $entity->id());

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getTitle(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('@label @title', [
          '@label' => $this->entity->label(),
          '@title' => $this->pluginDefinition['title'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBaseRouteParameters(): array
    {
        return [$this->entityType => $this->entity->id()];
    }

    /**
     * {@inheritdoc}
     */
    public function setType(string $entity_type_id): bool
    {
        if (isset($this->entityType)) {
            return false;
        }
        $this->entityType = $entity_type_id;
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->entityType;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeName(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        $entity_type_info = $this->entityTypeManager->getDefinition($this->entityType);
        $label = $entity_type_info->getLabel();
        return $label instanceof \Drupal\Core\StringTranslation\TranslatableMarkup ? $label : new \Drupal\Core\StringTranslation\TranslatableMarkup((string) $label);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeLabel(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        $entityType = $this->entityTypeManager->getDefinition($this->entityType);
        $label = $entityType->getLabel();
        return $label instanceof \Drupal\Core\StringTranslation\TranslatableMarkup ? $label : new \Drupal\Core\StringTranslation\TranslatableMarkup((string) $label);
    }

    /**
     * {@inheritdoc}
     */
    public function getOperations(): array
    {
        return [
          'list' => [
            'title' => $this->t('List'),
            'url' => Url::fromRoute('config_translation.entity_list', [
              'mapper_id' => $this->getPluginId(),
            ]),
          ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getContextualLinkGroup(): ?string
    {
        // @todo Contextual groups do not map to entity types in a predictable
        //   way. See https://www.drupal.org/node/2134841 to make them predictable.
        return match ($this->entityType) {
            'menu', 'block' => $this->entityType,
            'view' => 'entity.view.edit_form',
            default => null,
        };
    }

    /**
     * {@inheritdoc}
     */
    public function getOverviewRouteName(): string
    {
        return 'entity.' . $this->entityType . '.config_translation_overview';
    }

    /**
     * {@inheritdoc}
     */
    protected function processRoute(Route $route)
    {
        // Add entity upcasting information.
        $parameters = $route->getOption('parameters') ?: [];
        $parameters += [
          $this->entityType => [
            'type' => 'entity:' . $this->entityType,
          ],
        ];
        $route->setOption('parameters', $parameters);
    }

}
