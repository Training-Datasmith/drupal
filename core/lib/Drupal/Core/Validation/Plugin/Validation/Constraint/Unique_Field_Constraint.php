<?php

declare(strict_types=1);

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks if an entity field has a unique value.
 */
#[Constraint(
    id: 'UniqueField',
    label: new TranslatableMarkup('Unique field constraint', [], ['context' => 'Validation'])
)]
class UniqueFieldConstraint extends SymfonyConstraint
{
    /**
     * This constraint is case-insensitive by default.
     *
     * For example "FOO" and "foo" would be considered as equivalent, and
     * validation of the constraint would fail.
     *
     * @var bool
     */
    public $caseSensitive = false;

    public function __construct(
        mixed $options = null,
        ?bool $caseSensitive = null,
        public $message = 'A @entity_type with @field_name %value already exists.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
        $this->caseSensitive = $caseSensitive ?? $this->caseSensitive;
    }

    /**
     * {@inheritdoc}
     */
    public function validatedBy(): string
    {
        return \Drupal\Core\Validation\Plugin\Validation\Constraint\UniqueFieldValueValidator::class;
    }

}
