<?php

declare(strict_types=1);

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks for valid serialized data.
 */
#[Constraint(
    id: 'Serialized',
    label: new TranslatableMarkup('Serialized', [], ['context' => 'Validation'])
)]
class SerializedConstraint extends SymfonyConstraint
{
    public function __construct(
        mixed $options = null,
        public string $message = 'This value should be a serialized object.',
        public string $wrongTypeMessage = 'This value should be a string, "{type}" given.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
    }

}
