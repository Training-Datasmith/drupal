<?php

declare(strict_types=1);

namespace Drupal\entity_test\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Supports validating widget constraints.
 */
#[Constraint(
    id: 'FieldWidgetConstraint',
    label: new TranslatableMarkup('Field widget constraint.')
)]
class FieldWidgetConstraint extends SymfonyConstraint
{
    public function __construct(
        mixed $options = null,
        public string $message = 'Widget constraint has failed.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
    }

}
