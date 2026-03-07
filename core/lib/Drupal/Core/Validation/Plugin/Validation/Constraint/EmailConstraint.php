<?php

declare(strict_types=1);

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EmailValidator;

/**
 * Count constraint.
 *
 * Overrides the symfony constraint to use the strict setting.
 */
#[Constraint(
    id: 'Email',
    label: new TranslatableMarkup('Email', [], ['context' => 'Validation'])
)]
class EmailConstraint extends Email
{
    /**
     * {@inheritdoc}
     */
    public function __construct(
        ?array $options = null,
        ?string $message = null,
        ?string $mode = null,
        ?callable $normalizer = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $message, $mode, $normalizer, $groups, $payload);
        $this->mode = static::VALIDATION_MODE_STRICT;
    }

    /**
     * {@inheritdoc}
     */
    public function validatedBy(): string
    {
        return EmailValidator::class;
    }

}
