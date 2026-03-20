<?php

declare(strict_types=1);

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Supports validating all primitive types.
 */
#[Constraint(
    id: 'PrimitiveType',
    label: new TranslatableMarkup('Primitive type', [], ['context' => 'Validation'])
)]
class PrimitiveTypeConstraint extends SymfonyConstraint
{
    public function __construct(
        mixed $options = null,
        public $message = 'This value should be of the correct primitive type.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
    }

}
