<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks if a value is a valid entity type.
 */
#[Constraint(
    id: 'EntityType',
    label: new TranslatableMarkup('Entity type', [], ['context' => 'Validation']),
    type: ['entity', 'entity_reference']
)]
class EntityTypeConstraint extends SymfonyConstraint
{
    /**
     * The entity type option.
     *
     * @var string
     */
    public $type;

    public function __construct(
        mixed $options = null,
        ?string $type = null,
        public $message = 'The entity must be of type %type.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
        $this->type = $type ?? $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption(): ?string
    {
        return 'type';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredOptions(): array
    {
        return ['type'];
    }

}
