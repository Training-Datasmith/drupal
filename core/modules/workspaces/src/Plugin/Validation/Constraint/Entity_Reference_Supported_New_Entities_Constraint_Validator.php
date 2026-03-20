<?php

declare(strict_types=1);

namespace Drupal\workspaces\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if new entities created for entity reference fields are supported.
 */
class EntityReferenceSupportedNewEntitiesConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface
{
    public function __construct(
        /**
         * The workspace manager.
         */
        protected \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager,
        /**
         * The entity type manager.
         */
        protected \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager,
        /**
         * The workspace information service.
         */
        protected \Drupal\workspaces\WorkspaceInformationInterface $workspaceInfo
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('workspaces.manager'),
            $container->get('entity_type.manager'),
            $container->get('workspaces.information')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint): void
    {
        // The validator should run only if we are in a active workspace context.
        if (!$this->workspaceManager->hasActiveWorkspace()) {
            return;
        }

        $target_entity_type_id = $value->getFieldDefinition()->getFieldStorageDefinition()->getSetting('target_type');
        $target_entity_type = $this->entityTypeManager->getDefinition($target_entity_type_id);

        if ($value->hasNewEntity() && !$this->workspaceInfo->isEntityTypeSupported($target_entity_type)) {
            $this->context->addViolation($constraint->message, ['%collection_label' => $target_entity_type->getCollectionLabel()]);
        }
    }

}
