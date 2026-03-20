<?php

declare(strict_types=1);

namespace Drupal\taxonomy\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;

/**
 * Validation constraint for changing the term hierarchy in pending revisions.
 */
#[Constraint(
    id: 'TaxonomyHierarchy',
    label: new TranslatableMarkup('Taxonomy term hierarchy', [], ['context' => 'Validation'])
)]
class TaxonomyTermHierarchyConstraint extends CompositeConstraintBase
{
    public function __construct(mixed $options = null, public string $message = 'You can only change the hierarchy for the <em>published</em> version of this term.', ?array $groups = null, mixed $payload = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function coversFields(): array
    {
        return ['parent', 'weight'];
    }

}
