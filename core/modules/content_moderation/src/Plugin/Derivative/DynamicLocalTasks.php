<?php

declare(strict_types=1);

namespace Drupal\content_moderation\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Generates moderation-related local tasks.
 */
class DynamicLocalTasks extends DeriverBase implements ContainerDeriverInterface
{
    use StringTranslationTrait;

    /**
     * The router.
     *
     * @var \Symfony\Component\Routing\RouterInterface
     */
    protected $router;

    /**
     * Creates a FieldUiLocalTask object.
     *
     * @param string $basePluginId
     *   The base plugin ID.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager.
     * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
     *   The translation manager.
     * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInfo
     *   The moderation information service.
     * @param \Symfony\Component\Routing\RouterInterface $router
     *   The router.
     */
    public function __construct(/**
   * The base plugin ID.
   */
        protected $basePluginId,
        protected \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager,
        TranslationInterface $string_translation,
        protected \Drupal\content_moderation\ModerationInformationInterface $moderationInfo,
        RouterInterface $router
    ) {
        $this->stringTranslation = $string_translation;
        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, $base_plugin_id): static
    {
        return new static(
            $base_plugin_id,
            $container->get('entity_type.manager'),
            $container->get('string_translation'),
            $container->get('content_moderation.moderation_information'),
            $container->get('router')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDerivativeDefinitions($base_plugin_definition)
    {
        $this->derivatives = [];

        // Add the moderated content task if the route exists.
        if ($this->router->getRouteCollection()->get('content_moderation.admin_moderated_content') !== null) {
            $this->derivatives['content_moderation.moderated_content'] = [
              'route_name' => 'content_moderation.admin_moderated_content',
              'title' => $this->t('Moderated content'),
              'parent_id' => 'system.admin_content',
              'weight' => 1,
            ];
        }

        // Add the latest version tab to entities.
        $latest_version_entities = array_filter($this->entityTypeManager->getDefinitions(), fn (EntityTypeInterface $type) => $this->moderationInfo->canModerateEntitiesOfEntityType($type) && $type->hasLinkTemplate('latest-version'));

        foreach ($latest_version_entities as $entity_type_id => $entity_type) {
            $this->derivatives["$entity_type_id.latest_version_tab"] = [
              'route_name' => "entity.$entity_type_id.latest_version",
              'title' => $this->t('Latest version'),
              'base_route' => "entity.$entity_type_id.canonical",
              'weight' => 1,
            ] + $base_plugin_definition;
        }

        return $this->derivatives;
    }

}
