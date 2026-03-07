<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Entity Reference valid reference constraint.
 *
 * Verifies that referenced entities are valid.
 */
#[Constraint(
    id: 'ReferenceAccess',
    label: new TranslatableMarkup('Entity Reference reference access', [], ['context' => 'Validation'])
)]
class ReferenceAccessConstraint extends SymfonyConstraint
{
    public function __construct(
        mixed $options = null,
        public $message = 'You do not have access to the referenced entity (%type: %id).',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
    }

}
