<?php

declare(strict_types=1);

namespace Drupal\menu_link_content\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;

/**
 * Validation constraint for changing the menu hierarchy in pending revisions.
 */
#[Constraint(
    id: 'MenuTreeHierarchy',
    label: new TranslatableMarkup('Menu tree hierarchy.', [], ['context' => 'Validation'])
)]
class MenuTreeHierarchyConstraint extends CompositeConstraintBase
{
    public function __construct(mixed $options = null, public $message = 'You can only change the hierarchy for the <em>published</em> version of this menu link.', ?array $groups = null, mixed $payload = null)
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
