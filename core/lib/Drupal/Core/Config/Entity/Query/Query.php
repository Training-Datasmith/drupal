<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Entity\Query;

use Drupal\Core\Entity\Entity_Type_Interface;
use Drupal\Core\Entity\Query\Query_Base;
use Drupal\Core\Entity\Query\Query_Interface;
/**
 * Defines the entity query for configuration entities.
 */
class Query extends Query_Base implements Query_Interface
{
    /**
     * Information about the entity type.
     *
     * @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface
     */
    protected $entity_type;
    /**
     * Constructs a Query object.
     *
     * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
     *   The entity type definition.
     * @param string $conjunction
     *   - AND: all of the conditions on the query need to match.
     *   - OR: at least one of the conditions on the query need to match.
     * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
     *   The config factory.
     * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
     *   The key value factory.
     * @param array $namespaces
     *   List of potential namespaces of the classes belonging to this query.
     */
    public function __construct(Entity_Type_Interface $entity_type, $conjunction, protected \Drupal\Core\Config\Config_Factory_Interface $config_factory, protected \Drupal\Core\Key_Value_Store\Key_Value_Factory_Interface $key_value_factory, array $namespaces)
    {
        parent::__construct($entity_type, $conjunction, $namespaces);
    }
    /**
     * Overrides \Drupal\Core\Entity\Query\QueryBase::condition().
     *
     * Additional to the syntax defined in the QueryInterface you can use
     * placeholders (*) to match all keys of a subarray. Let's take the follow
     * yaml file as example:
     * @code
     *  level1:
     *    level2a:
     *      level3: 1
     *    level2b:
     *      level3: 2
     * @endcode
     * Then you can filter out via $query->condition('level1.*.level3', 1).
     */
    public function condition($property, $value = null, $operator = null, $langcode = null)
    {
        return parent::condition($property, $value, $operator, $langcode);
    }
    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        // Invoke entity query alter hooks.
        $this->alter();
        // Load the relevant config records.
        $configs = $this->load_records();
        // Apply conditions.
        $result = $this->condition->compile($configs);
        // Apply sort settings.
        foreach ($this->sort as $sort) {
            $direction = $sort['direction'] == 'ASC' ? -1 : 1;
            $field = $sort['field'];
            uasort($result, function (array $a, array $b) use ($field, $direction): int {
                $properties = explode('.', (string) $field);
                foreach ($properties as $property) {
                    if (isset($a[$property]) || isset($b[$property])) {
                        $a = $a[$property] ?? null;
                        $b = $b[$property] ?? null;
                    }
                }
                return $a <= $b ? $direction : -$direction;
            });
        }
        // Let the pager do its work.
        $this->initialize_pager();
        if ($this->range) {
            $result = array_slice($result, $this->range['start'], $this->range['length'], true);
        }
        if ($this->count) {
            return count($result);
        }
        // Create the expected structure of entity_id => entity_id. Config
        // entities have string entity IDs.
        foreach ($result as $key => &$value) {
            $value = (string) $key;
        }
        return $result;
    }
    /**
     * Loads the config records to examine for the query.
     *
     * @return array
     *   Config records keyed by entity IDs.
     */
    protected function load_records(): array
    {
        $prefix = $this->entity_type->get_config_prefix() . '.';
        $prefix_length = strlen($prefix);
        // Search the conditions for restrictions on configuration object names.
        $filter_by_names = [];
        $has_added_restrictions = false;
        $id_condition = null;
        $id_key = $this->entity_type->get_key('id');
        if ($this->condition->get_conjunction() == 'AND') {
            $lookup_keys = $this->entity_type->get_lookup_keys();
            $conditions = $this->condition->conditions();
            foreach ($conditions as $condition_key => $condition) {
                $operator = $condition['operator'] ?: (is_array($condition['value']) ? 'IN' : '=');
                if (is_string($condition['field']) && ($operator == 'IN' || $operator == '=')) {
                    // Special case ID lookups.
                    if ($condition['field'] == $id_key) {
                        $has_added_restrictions = true;
                        $ids = (array) $condition['value'];
                        $filter_by_names[] = array_map(static fn($id) => $prefix . $id, $ids);
                    } elseif (in_array($condition['field'], $lookup_keys)) {
                        $has_added_restrictions = true;
                        // If we don't find anything then there are no matches. No point in
                        // listing anything.
                        $keys = (array) $condition['value'];
                        $keys = array_map(static fn($value) => $condition['field'] . ':' . $value, $keys);
                        foreach ($this->get_config_key_store()->get_multiple($keys) as $list) {
                            $filter_by_names[] = $list;
                        }
                    }
                } elseif (!$id_condition && $condition['field'] == $id_key) {
                    $id_condition = $condition;
                }
                // We stop at the first restricting condition on name. In the case where
                // there are additional restricting conditions, results will be
                // eliminated when the conditions are checked on the loaded records.
                if ($has_added_restrictions !== false) {
                    // If the condition has been responsible for narrowing the list of
                    // configuration to check there is no point in checking it further.
                    unset($conditions[$condition_key]);
                    break;
                }
            }
        }
        // If no restrictions on IDs were found, we need to parse all records.
        if ($has_added_restrictions === false) {
            $filter_by_names = $this->config_factory->list_all($prefix);
        } else {
            $filter_by_names = array_merge(...$filter_by_names);
        }
        // In case we have an ID condition, try to narrow down the list of config
        // objects to load.
        if ($id_condition && !empty($filter_by_names)) {
            $value = $id_condition['value'];
            $filter = null;
            switch ($id_condition['operator']) {
                case '<>':
                    $filter = static function ($name) use ($value, $prefix_length): bool {
                        $id = substr($name, $prefix_length);
                        return $id !== $value;
                    };
                    break;
                case 'STARTS_WITH':
                    $filter = static function ($name) use ($value, $prefix_length): bool {
                        $id = substr($name, $prefix_length);
                        return str_starts_with($id, (string) $value);
                    };
                    break;
                case 'CONTAINS':
                    $filter = static function ($name) use ($value, $prefix_length): bool {
                        $id = substr($name, $prefix_length);
                        return str_contains($id, (string) $value);
                    };
                    break;
                case 'ENDS_WITH':
                    $filter = static function ($name) use ($value, $prefix_length): bool {
                        $id = substr($name, $prefix_length);
                        return str_ends_with($id, (string) $value);
                    };
                    break;
            }
            if ($filter) {
                $filter_by_names = array_filter($filter_by_names, $filter);
            }
        }
        // Load the corresponding records.
        $records = [];
        foreach ($this->config_factory->load_multiple($filter_by_names) as $config) {
            $records[substr($config->get_name(), $prefix_length)] = $config->get();
        }
        return $records;
    }
    /**
     * Gets the key value store used to store fast lookups.
     *
     * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
     *   The key value store used to store fast lookups.
     */
    protected function get_config_key_store()
    {
        return $this->key_value_factory->get(Query_Factory::CONFIG_LOOKUP_PREFIX . $this->entity_type_id);
    }
}