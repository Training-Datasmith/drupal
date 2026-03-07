<?php

declare(strict_types=1);

namespace Drupal\user\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\Core\Validation\Plugin\Validation\Constraint\UniqueFieldConstraint;

/**
 * Checks if a user name is unique on the site.
 */
#[Constraint(
    id: 'UserNameUnique',
    label: new TranslatableMarkup('User name unique', [], ['context' => 'Validation'])
)]
class UserNameUnique extends UniqueFieldConstraint
{
    public function __construct(
        mixed $options = null,
        ?bool $caseSensitive = null,
        $message = 'The username %value is already taken.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $caseSensitive, $message, $groups, $payload);
    }

}
