<?php

declare(strict_types=1);

namespace Drupal\Core\Path\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validation constraint for valid system paths.
 */
#[Constraint(
    id: 'ValidPath',
    label: new TranslatableMarkup('Valid path.', [], ['context' => 'Validation'])
)]
class ValidPathConstraint extends SymfonyConstraint
{
    public function __construct(
        mixed $options = null,
        public $message = "Either the path '%link_path' is invalid or you do not have access to it.",
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
    }

}
