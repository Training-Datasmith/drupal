<?php

declare(strict_types=1);

namespace Drupal\comment\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;

/**
 * Supports validating comment author names.
 */
#[Constraint(
    id: 'CommentName',
    label: new TranslatableMarkup('Comment author name', [], ['context' => 'Validation']),
    type: 'entity:comment'
)]
class CommentNameConstraint extends CompositeConstraintBase
{
    public function __construct(mixed $options = null, public $messageNameTaken = 'The name you used (%name) belongs to a registered user.', public $messageRequired = 'You have to specify a valid author.', public $messageMatch = 'The specified author name does not match the comment author.', ?array $groups = null, mixed $payload = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function coversFields(): array
    {
        return ['name', 'uid'];
    }

}
