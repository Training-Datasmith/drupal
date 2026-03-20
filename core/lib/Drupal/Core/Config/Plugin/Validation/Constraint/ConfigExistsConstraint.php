<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Plugin\Validation\Constraint;

use Drupal\Core\String_Translation\Translatable_Markup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;
/**
 * Checks that the value is the name of an existing config object.
 */
#[Constraint(id: 'ConfigExists', label: new Translatable_Markup('Config exists', [], ['context' => 'Validation']))]
class Config_Exists_Constraint extends Symfony_Constraint
{
    /**
     * Optional prefix, to be specified when this contains a config entity ID.
     *
     * Every config entity type can have multiple instances, all with unique IDs
     * but the same config prefix. When config refers to a config entity,
     * typically only the ID is stored, not the prefix.
     */
    public string $prefix = '';
    public function __construct(mixed $options = null, ?string $prefix = null, public string $message = "The '@name' config does not exist.", ?array $groups = null, mixed $payload = null)
    {
        parent::__construct($options, $groups, $payload);
        $this->prefix = $prefix ?? $this->prefix;
    }
}