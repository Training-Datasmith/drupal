<?php

declare(strict_types=1);

namespace Drupal\ckeditor5\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * A CKEditor 5 toolbar item.
 *
 * @internal
 */
#[Constraint(
    id: 'CKEditor5ToolbarItemDependencyConstraint',
    label: new TranslatableMarkup('CKEditor 5 toolbar item dependency', [], ['context' => 'Validation'])
)]
class ToolbarItemDependencyConstraint extends SymfonyConstraint
{
    /**
     * The toolbar item that this validation constraint requires to be enabled.
     *
     * @var null|string
     */
    public $toolbarItem;

    public function __construct(
        mixed $options = null,
        ?string $toolbarItem = null,
        public $message = 'Depends on %toolbar_item, which is not enabled.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
        $this->toolbarItem = $toolbarItem ?? $this->toolbarItem;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredOptions(): array
    {
        return ['toolbarItem'];
    }

}
