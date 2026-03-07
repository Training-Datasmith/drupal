<?php

declare(strict_types=1);

namespace Drupal\field;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Rebuilds all form and view modes for a passed entity bundle.
 *
 * @see field_field_config_insert()
 *
 * @internal
 */
class EntityDisplayRebuilder implements ContainerInjectionInterface
{
    /**
     * Constructs a new EntityDisplayRebuilder.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity manager.
     * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
     *   The entity display repository.
     * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
     *   The entity type bundle info.
     */
    public function __construct(
        /**
         * The field storage config storage.
         */
        protected \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager,
        /**
         * The display repository.
         */
        protected \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository,
        protected \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('entity_type.manager'),
            $container->get('entity_display.repository'),
            $container->get('entity_type.bundle.info')
        );
    }

    /**
     * Rebuild displays for single Entity Type.
     *
     * @param string $entity_type_id
     *   The entity type machine name.
     * @param string $bundle
     *   The bundle we need to rebuild.
     */
    public function rebuildEntityTypeDisplays($entity_type_id, $bundle): void
    {
        // Get the displays.
        $view_modes = $this->entityDisplayRepository->getViewModeOptions($entity_type_id);
        $form_modes = $this->entityDisplayRepository->getFormModeOptions($entity_type_id);

        // Save view mode displays.
        $view_mode_ids = array_map(fn (int|string $view_mode) => "$entity_type_id.$bundle.$view_mode", array_keys($view_modes));
        foreach ($this->entityTypeManager->getStorage('entity_view_display')->loadMultiple($view_mode_ids) as $display) {
            $display->save();
        }
        // Save form mode displays.
        $form_mode_ids = array_map(fn (int|string $form_mode) => "$entity_type_id.$bundle.$form_mode", array_keys($form_modes));
        foreach ($this->entityTypeManager->getStorage('entity_form_display')->loadMultiple($form_mode_ids) as $display) {
            $display->save();
        }
    }

}
