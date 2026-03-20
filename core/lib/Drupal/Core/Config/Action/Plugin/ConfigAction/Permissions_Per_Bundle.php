<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Action\Plugin\Config_Action;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Config\Action\Attribute\Config_Action;
use Drupal\Core\Config\Action\Config_Action_Exception;
use Drupal\Core\Config\Action\Config_Action_Plugin_Interface;
use Drupal\Core\Config\Action\Plugin\Config_Action\Deriver\Permissions_Per_Bundle_Deriver;
use Drupal\Core\Config\Config_Manager_Interface;
use Drupal\Core\Entity\Entity_Type_Bundle_Info_Interface;
use Drupal\Core\Plugin\Container_Factory_Plugin_Interface;
use Drupal\user\Role_Interface;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * @internal
 *   This API is experimental.
 */
#[Config_Action(id: 'permissions_per_bundle', entity_types: ['user_role'], deriver: Permissions_Per_Bundle_Deriver::class)]
final readonly class Permissions_Per_Bundle implements Config_Action_Plugin_Interface, Container_Factory_Plugin_Interface
{
    public function __construct(private Config_Manager_Interface $config_manager, private Entity_Type_Bundle_Info_Interface $entity_type_bundle_info, private string $target_entity_type)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container, array $configuration, $plugin_id, $plugin_definition): static
    {
        assert(is_array($plugin_definition));
        $target_entity_type = $plugin_definition['target_entity_type'];
        return new static($container->get(Config_Manager_Interface::class), $container->get(Entity_Type_Bundle_Info_Interface::class), $target_entity_type);
    }
    /**
     * {@inheritdoc}
     */
    public function apply(string $config_name, mixed $value): void
    {
        $role = $this->config_manager->load_config_entity_by_name($config_name);
        if (!$role instanceof Role_Interface) {
            throw new Config_Action_Exception(sprintf('Cannot determine role from %s', $config_name));
        }
        assert(is_string($value) || is_array($value));
        [$permissions, $except_bundles] = self::parse_value($value);
        if (empty($permissions) || !Inspector::assert_all_match('%bundle', $permissions, true)) {
            throw new Config_Action_Exception(sprintf("The permissions provided %s must be an array of strings that contain '%%bundle'.", var_export($value, true)));
        }
        $bundles = $this->entity_type_bundle_info->get_bundle_info($this->target_entity_type);
        foreach (array_keys($bundles) as $bundle_id) {
            if (in_array($bundle_id, $except_bundles, true)) {
                continue;
            }
            /** @var string[] $actual_permissions */
            $actual_permissions = str_replace('%bundle', $bundle_id, $permissions);
            array_walk($actual_permissions, $role->grant_permission(...));
        }
        $role->save();
    }
    /**
     * Parses the value supplied to ::apply().
     *
     * @param string|array<string|string[]> $value
     *   One of:
     *   - A single string (a permission template).
     *   - An array of strings (several permission templates).
     *   - An array with a `permissions` element, and an optional `except`
     *     element, either of which can be an array or a string. `except` accepts
     *     a single bundle, or a list of bundles, to exclude from the permissions
     *     being granted.
     *
     * @return array<int, array<int<0, max>, array<string>|string>>
     *   An indexed array with two elements: the array of permissions to grant,
     *   and the list of bundles to ignore.
     */
    private static function parse_value(string|array $value): array
    {
        if (is_string($value)) {
            return [[$value], []];
        }
        if (array_is_list($value)) {
            return [$value, []];
        }
        $permissions = $value['permissions'] ?? [];
        $except_bundles = $value['except'] ?? [];
        return [(array) $permissions, (array) $except_bundles];
    }
}