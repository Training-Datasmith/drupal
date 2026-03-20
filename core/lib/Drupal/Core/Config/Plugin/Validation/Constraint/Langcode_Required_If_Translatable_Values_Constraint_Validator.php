<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Plugin\Validation\Constraint;

use Drupal\Core\Config\Schema\Mapping;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraint_Validator;
use Symfony\Component\Validator\Exception\LogicException;
/**
 * Validates the LangcodeRequiredIfTranslatableValues constraint.
 */
final class Langcode_Required_If_Translatable_Values_Constraint_Validator extends Constraint_Validator
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        assert($constraint instanceof Langcode_Required_If_Translatable_Values_Constraint);
        $mapping = $this->context->get_object();
        assert($mapping instanceof Mapping);
        $root = $this->context->get_root();
        if ($mapping !== $root) {
            throw new LogicException(sprintf('The LangcodeRequiredIfTranslatableValues constraint is applied to \'%s\'. This constraint can only operate on the root object being validated.', $root->get_name() . '::' . $mapping->get_name()));
        }
        assert(in_array('langcode', $mapping->get_valid_keys(), true));
        $is_translatable = $mapping->has_translatable_elements();
        if ($is_translatable && !array_key_exists('langcode', $value)) {
            $this->context->build_violation($constraint->missing_message)->set_parameter('@name', $mapping->get_name())->add_violation();
            return;
        }
        if (!$is_translatable && array_key_exists('langcode', $value)) {
            // @todo Convert this deprecation to an actual validation error in
            //   https://www.drupal.org/project/drupal/issues/3440238.
            // phpcs:ignore
            @trigger_error(str_replace('@name', $mapping->get_name(), $constraint->superfluous_message), E_USER_DEPRECATED);
        }
    }
}