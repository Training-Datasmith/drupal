<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Schema;

use Drupal\Core\Config\Entity\Config_Entity_Interface;
use Drupal\Core\Config\Entity\Config_Entity_Type;
use Drupal\Core\Config\Typed_Config_Manager_Interface;
use Drupal\Core\Entity\Plugin\Data_Type\Config_Entity_Adapter;
use Drupal\Core\Typed_Data\Primitive_Interface;
use Drupal\Core\Typed_Data\Traversable_Typed_Data_Interface;
use Drupal\Core\Typed_Data\Type\Boolean_Interface;
use Drupal\Core\Typed_Data\Type\Float_Interface;
use Drupal\Core\Typed_Data\Type\Integer_Interface;
use Drupal\Core\Typed_Data\Type\String_Interface;
use Symfony\Component\Validator\Constraint_Violation_Interface;
/**
 * Provides a trait for checking configuration schema.
 */
trait Schema_Check_Trait
{
    /**
     * The config schema wrapper object for the configuration object under test.
     */
    protected Traversable_Typed_Data_Interface $schema;
    /**
     * The configuration object name under test.
     */
    protected string $config_name;
    /**
     * The ignored property paths.
     *
     * Allow ignoring specific config schema types (top-level keys, require an
     * exact match to one of the top-level entries in *.schema.yml files) by
     * allowing one or more partial property path matches.
     *
     * Keys must be an exact match for a Config object's schema type.
     * Values must be wildcard matches for property paths, where any property
     * path segment can use a wildcard (`*`) to indicate any value for that
     * segment should be accepted for this property path to be ignored.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    protected static array $ignored_property_paths = ['search.page.*' => [
        // @todo Fix config or tweak schema of `type: search.page.*` in
        //   https://drupal.org/i/3380475.
        // @see search.schema.yml
        'label' => ['This value should not be blank.'],
    ], 'contact.settings' => [
        // @todo Simple config cannot have dependencies on any other config.
        //   Remove this in https://www.drupal.org/project/drupal/issues/3425992.
        'default_form' => ["The 'contact.form.feedback' config does not exist."],
    ], 'editor.editor.*' => [
        // @todo Fix stream wrappers not being available early enough in
        //   https://www.drupal.org/project/drupal/issues/3416735
        'image_upload.scheme' => ['^The file storage you selected is not a visible, readable and writable stream wrapper\. Possible choices: <em class="placeholder"><\/em>\.$'],
    ], 'search.settings' => [
        // @todo Simple config cannot have dependencies on any other config.
        //   Remove this in https://www.drupal.org/project/drupal/issues/3425992.
        'default_page' => ["The 'search.page.node_search' config does not exist."],
    ]];
    /**
     * Checks the TypedConfigManager has a valid schema for the configuration.
     *
     * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
     *   The TypedConfigManager.
     * @param string $config_name
     *   The configuration name.
     * @param array $config_data
     *   The configuration data, assumed to be data for a top-level config object.
     * @param bool $validate_constraints
     *   Determines if constraints will be validated. If TRUE, constraint
     *   validation errors will be added to the errors found.
     *
     * @return array|bool
     *   FALSE if no schema found. List of errors if any found. TRUE if fully
     *   valid.
     */
    public function check_config_schema(Typed_Config_Manager_Interface $typed_config, $config_name, array $config_data, bool $validate_constraints = false): bool|array
    {
        $this->config_name = $config_name;
        if (!$typed_config->has_config_schema($config_name)) {
            return false;
        }
        $this->schema = $typed_config->create_from_name_and_data($config_name, $config_data);
        $errors = [];
        foreach ($config_data as $key => $value) {
            $errors[] = $this->check_value($key, $value);
        }
        $errors = array_merge(...$errors);
        if ($validate_constraints) {
            // Also perform explicit validation. Note this does NOT require every node
            // in the config schema tree to have validation constraints defined.
            $violations = $this->schema->validate();
            $filtered_violations = array_filter(iterator_to_array($violations), fn(Constraint_Violation_Interface $v): bool => !static::is_violation_for_ignored_property_path($v));
            $validation_errors = array_map(fn(Constraint_Violation_Interface $v): string => sprintf('[%s] %s', $v->get_property_path(), (string) $v->get_message()), $filtered_violations);
            // @todo Decide in https://www.drupal.org/project/drupal/issues/3395099 when/how to trigger deprecation errors or even failures for contrib modules.
            $errors = array_merge($errors, $validation_errors);
        }
        if (empty($errors)) {
            return true;
        }
        return $errors;
    }
    /**
     * Determines whether this violation is for an ignored Config property path.
     *
     * @param \Symfony\Component\Validator\ConstraintViolationInterface $v
     *   A validation constraint violation for a Config object.
     *
     * @return bool
     *   TRUE when the violation is for an ignored configuration property path,
     *   FALSE otherwise.
     */
    protected static function is_violation_for_ignored_property_path(Constraint_Violation_Interface $v): bool
    {
        // When the validated object is a config entity wrapped in a
        // ConfigEntityAdapter, some work is necessary to map from e.g.
        // `entity:comment_type` to the corresponding `comment.type.*`.
        if ($v->get_root() instanceof Config_Entity_Adapter) {
            $config_entity = $v->get_root()->get_entity();
            assert($config_entity instanceof Config_Entity_Interface);
            $config_entity_type = $config_entity->get_entity_type();
            assert($config_entity_type instanceof Config_Entity_Type);
            $config_prefix = $config_entity_type->get_config_prefix();
            // Compute the data type of the config object being validated:
            // - the config entity type's config prefix
            // - with as many `.*`-suffixes appended as there are parts in the ID (for
            //   example, for NodeType there's only 1 part, for EntityViewDisplay
            //   there are 3 parts.)
            // TRICKY: in principle it is possible to compute the exact number of
            // suffixes by inspecting ConfigEntity::getConfigDependencyName(), except
            // when the entity ID itself is invalid. Unfortunately that means
            // gradually discovering it is the only available alternative.
            $suffix_count = 1;
            do {
                $config_object_data_type = $config_prefix . str_repeat('.*', $suffix_count);
                $suffix_count++;
            } while ($suffix_count <= 3 && !array_key_exists($config_object_data_type, static::$ignored_property_paths));
        } else {
            $config_object_data_type = $v->get_root()->get_data_definition()->get_data_type();
        }
        if (!array_key_exists($config_object_data_type, static::$ignored_property_paths)) {
            return false;
        }
        foreach (static::$ignored_property_paths[$config_object_data_type] as $ignored_property_path_expression => $ignored_validation_constraint_messages) {
            // Convert the wildcard-based expression to a regex: treat `*` nor in the
            // regex sense nor as something to be escaped: treat it as the wildcard
            // for a segment in a property path (property path segments are separated
            // by periods).
            // That requires first ensuring that preg_quote() does not escape it, and
            // then replacing it with an appropriate regular expression: `[^\.]+`,
            // which means: ">=1 characters that are anything except a period".
            $ignored_property_path_regex = str_replace(' ', '[^\.]+', preg_quote(str_replace('*', ' ', $ignored_property_path_expression)));
            // To ignore this violation constraint, require a match on both the
            // property path and the message.
            $property_path_match = preg_match('/^' . $ignored_property_path_regex . '$/', $v->get_property_path(), $matches) === 1;
            if ($property_path_match) {
                return preg_match(sprintf('/^(%s)$/', implode('|', $ignored_validation_constraint_messages)), (string) $v->get_message()) === 1;
            }
        }
        return false;
    }
    /**
     * Helper method to check data type.
     *
     * @param string $key
     *   A string of configuration key.
     * @param mixed $value
     *   Value of given key.
     *
     * @return array
     *   List of errors found while checking with the corresponding schema.
     */
    protected function check_value(string $key, $value): array
    {
        $error_key = $this->config_name . ':' . $key;
        /** @var \Drupal\Core\TypedData\TypedDataInterface $element */
        $element = $this->schema->get($key);
        // Check if this type has been deprecated.
        $data_definition = $element->get_data_definition();
        if (!empty($data_definition['deprecated'])) {
            @trigger_error($data_definition['deprecated'], E_USER_DEPRECATED);
        }
        if ($element instanceof Undefined) {
            return [$error_key => 'missing schema'];
        }
        // Do not check value if it is defined to be ignored.
        if ($element && $element instanceof Ignore) {
            return [];
        }
        if ($element && is_scalar($value) || $value === null) {
            $success = false;
            $type = gettype($value);
            if ($element instanceof Primitive_Interface) {
                $success = $type == 'integer' && $element instanceof Integer_Interface || ($type == 'double' || $type == 'integer') && $element instanceof Float_Interface || $type == 'boolean' && $element instanceof Boolean_Interface || $type == 'string' && $element instanceof String_Interface || $value === null;
            } elseif ($element instanceof Array_Element && $element->is_nullable() && $value === null) {
                $success = true;
            }
            $class = $element::class;
            if (!$success) {
                return [$error_key => "variable type is {$type} but applied schema class is {$class}"];
            }
        } else {
            $errors = [];
            if (!$element instanceof Traversable_Typed_Data_Interface) {
                $errors[$error_key] = 'non-scalar value but not defined as an array (such as mapping or sequence)';
            }
            // Go on processing so we can get errors on all levels. Any non-scalar
            // value must be an array so cast to an array.
            if (!is_array($value)) {
                $value = (array) $value;
            }
            $nested_errors = [];
            // Recurse into any nested keys.
            foreach ($value as $nested_value_key => $nested_value) {
                $nested_errors[] = $this->check_value($key . '.' . $nested_value_key, $nested_value);
            }
            return array_merge($errors, ...$nested_errors);
        }
        // No errors found.
        return [];
    }
}