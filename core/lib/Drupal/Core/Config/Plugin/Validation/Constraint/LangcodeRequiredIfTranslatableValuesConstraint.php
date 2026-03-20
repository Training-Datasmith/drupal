<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Plugin\Validation\Constraint;

use Drupal\Core\String_Translation\Translatable_Markup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;
/**
 * Validation constraint for translatable configuration.
 */
#[Constraint(id: 'LangcodeRequiredIfTranslatableValues', label: new Translatable_Markup('Translatable config has langcode', [], ['context' => 'Validation']), type: ['config_object'])]
class Langcode_Required_If_Translatable_Values_Constraint extends Symfony_Constraint
{
    public function __construct(mixed $options = null, public string $missing_message = 'The @name config object must specify a language code, because it contains translatable values.', public string $superfluous_message = 'The @name config object does not contain any translatable values, so it should not specify a language code.', ?array $groups = null, mixed $payload = null)
    {
        parent::__construct($options, $groups, $payload);
    }
}