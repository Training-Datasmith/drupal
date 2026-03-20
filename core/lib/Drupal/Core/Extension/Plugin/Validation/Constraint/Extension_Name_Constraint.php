<?php

declare(strict_types=1);

namespace Drupal\Core\Extension\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\Core\Validation\Plugin\Validation\Constraint\RegexConstraint;

/**
 * Checks that the value is a valid extension name.
 */
#[Constraint(
    id: 'ExtensionName',
    label: new TranslatableMarkup('Valid extension name', [], ['context' => 'Validation']),
)]
class ExtensionNameConstraint extends RegexConstraint
{
}
