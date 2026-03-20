<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Plugin\Validation\Constraint;

use Drupal\Core\String_Translation\Translatable_Markup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;
/**
 * Checks that config dependencies contain specific types of entities.
 */
#[Constraint(id: 'RequiredConfigDependencies', label: new Translatable_Markup('Required config dependency types', [], ['context' => 'Validation']))]
class Required_Config_Dependencies_Constraint extends Symfony_Constraint
{
    /**
     * The IDs of entity types that need to exist in config dependencies.
     *
     * For example, if an entity requires a filter format in its config
     * dependencies, this should contain `filter_format`.
     *
     * @var string[]
     */
    public array $entity_types = [];
    public function __construct(mixed $options = null, ?array $entity_types = null, public string $message = 'This @entity_type requires a @dependency_type.', ?array $groups = null, mixed $payload = null)
    {
        parent::__construct($options, $groups, $payload);
        $this->entity_types = $entity_types ?? $this->entity_types;
    }
    /**
     * {@inheritdoc}
     */
    public function get_required_options(): array
    {
        return ['entityTypes'];
    }
    /**
     * {@inheritdoc}
     */
    public function get_default_option(): ?string
    {
        return 'entityTypes';
    }
}