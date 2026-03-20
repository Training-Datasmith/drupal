<?php

declare(strict_types=1);

namespace Drupal\user\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\Core\Validation\Plugin\Validation\Constraint\UniqueFieldConstraint;

/**
 * Checks if a user's email address is unique on the site.
 */
#[Constraint(
    id: 'UserMailUnique',
    label: new TranslatableMarkup('User email unique', [], ['context' => 'Validation'])
)]
class UserMailUnique extends UniqueFieldConstraint
{
    public function __construct(
        mixed $options = null,
        ?bool $caseSensitive = null,
        $message = 'The email address %value is already taken.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $caseSensitive, $message, $groups, $payload);
    }

}
