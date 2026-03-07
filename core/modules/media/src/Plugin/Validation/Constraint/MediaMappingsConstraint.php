<?php

declare(strict_types=1);

namespace Drupal\media\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates media mappings.
 *
 * @internal
 */
#[Constraint(
    id: 'MediaMappingsConstraint',
    label: new TranslatableMarkup('Media Mapping Constraint', [], ['context' => 'Validation']),
    type: 'string'
)]
class MediaMappingsConstraint extends SymfonyConstraint
{
    public function __construct(
        mixed $options = null,
        public string $invalidMappingMessage = 'It is not possible to map the source field @source_field_name of a media type.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
    }

}
