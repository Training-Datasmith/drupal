<?php

declare(strict_types=1);

namespace Drupal\file\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\Core\Validation\Plugin\Validation\Constraint\UniqueFieldConstraint;
use Drupal\Core\Validation\Plugin\Validation\Constraint\UniqueFieldValueValidator;

/**
 * Supports validating file URIs.
 */
#[Constraint(
    id: 'FileUriUnique',
    label: new TranslatableMarkup('File URI', [], ['context' => 'Validation'])
)]
class FileUriUnique extends UniqueFieldConstraint
{
    public function __construct(
        mixed $options = null,
        ?bool $caseSensitive = null,
        $message = 'The file %value already exists. Enter a unique file URI.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        $this->caseSensitive = $caseSensitive ?? true;
        parent::__construct($options, $caseSensitive, $message, $groups, $payload);

    }

    /**
     * {@inheritdoc}
     */
    public function validatedBy(): string
    {
        return UniqueFieldValueValidator::class;
    }

}
