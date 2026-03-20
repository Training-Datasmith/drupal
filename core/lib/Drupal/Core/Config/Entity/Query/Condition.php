<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Entity\Query;

use Drupal\Core\Entity\Query\Condition_Base;
use Drupal\Core\Entity\Query\Condition_Interface;
use Drupal\Core\Entity\Query\Query_Exception;
/**
 * Defines the condition class for the config entity query.
 *
 * @see \Drupal\Core\Config\Entity\Query\Query
 */
class Condition extends Condition_Base
{
    /**
     * {@inheritdoc}
     */
    public function compile($configs)
    {
        $and = strtoupper($this->conjunction) == 'AND';
        $single_conditions = [];
        $condition_groups = [];
        foreach ($this->conditions as $condition) {
            if ($condition['field'] instanceof Condition_Interface) {
                $condition_groups[] = $condition;
            } else {
                if (!isset($condition['operator'])) {
                    $condition['operator'] = is_array($condition['value']) ? 'IN' : '=';
                }
                // Process the value for operator that use it.
                if (!in_array($condition['operator'], ['IS NULL', 'IS NOT NULL'], true)) {
                    // Lowercase condition value(s) for case-insensitive matches.
                    if (is_array($condition['value'])) {
                        $condition['value'] = array_map(mb_strtolower(...), $condition['value']);
                    } elseif (!is_bool($condition['value'])) {
                        $condition['value'] = mb_strtolower((string) $condition['value']);
                    }
                }
                $single_conditions[] = $condition;
            }
        }
        $return = [];
        if ($single_conditions) {
            foreach ($configs as $config_name => $config) {
                foreach ($single_conditions as $condition) {
                    $match = $this->match_array($condition, $config, explode('.', (string) $condition['field']));
                    // If AND and it's not matching, then the rest of conditions do not
                    // matter and this config object does not match.
                    // If OR and it is matching, then the rest of conditions do not
                    // matter and this config object does match.
                    if ($and != $match) {
                        break;
                    }
                }
                if ($match) {
                    $return[$config_name] = $config;
                }
            }
        } elseif (!$condition_groups || $and) {
            // If there were no single conditions then either:
            // - Complex conditions, OR: need to start from no entities.
            // - Complex conditions, AND: need to start from all entities.
            // - No complex conditions (AND/OR doesn't matter): need to return all
            //   entities.
            $return = $configs;
        }
        foreach ($condition_groups as $condition) {
            $group_entities = $condition['field']->compile($configs);
            if ($and) {
                $return = array_intersect_key($return, $group_entities);
            } else {
                $return = $return + $group_entities;
            }
        }
        return $return;
    }
    /**
     * {@inheritdoc}
     */
    public function exists($field, $langcode = null)
    {
        return $this->condition($field, null, 'IS NOT NULL', $langcode);
    }
    /**
     * {@inheritdoc}
     */
    public function not_exists($field, $langcode = null)
    {
        return $this->condition($field, null, 'IS NULL', $langcode);
    }
    /**
     * Matches for an array representing one or more config paths.
     *
     * @param array $condition
     *   The condition array as created by the condition() method.
     * @param array $data
     *   The config array or part of it.
     * @param array $needs_matching
     *   The list of config array keys needing a match. Can contain config keys
     *   and the * wildcard.
     * @param array $parents
     *   The current list of parents.
     *
     * @return bool
     *   TRUE when the condition matched to the data else FALSE.
     */
    protected function match_array(array $condition, array $data, array $needs_matching, array $parents = []): bool
    {
        $parent = array_shift($needs_matching);
        if ($parent === '*') {
            $candidates = array_keys($data);
        } else {
            // Avoid a notice when calling match() later.
            if (!isset($data[$parent])) {
                $data[$parent] = null;
            }
            $candidates = [$parent];
        }
        foreach ($candidates as $key) {
            if ($needs_matching) {
                if (is_array($data[$key])) {
                    $new_parents = $parents;
                    $new_parents[] = $key;
                    if ($this->match_array($condition, $data[$key], $needs_matching, $new_parents)) {
                        return true;
                    }
                } elseif ($condition['operator'] === 'IS NULL') {
                    return true;
                }
            } elseif ($this->match($condition, $data[$key])) {
                return true;
            }
        }
        return false;
    }
    /**
     * Perform the actual matching.
     *
     * @param array $condition
     *   The condition array as created by the condition() method.
     * @param string $value
     *   The value to match against.
     *
     * @return bool
     *   TRUE when matches else FALSE.
     */
    protected function match(array $condition, $value)
    {
        // "IS NULL" and "IS NOT NULL" conditions can also deal with array values,
        // so we return early for them to avoid problems.
        if (in_array($condition['operator'], ['IS NULL', 'IS NOT NULL'], true)) {
            $should_be_set = $condition['operator'] === 'IS NOT NULL';
            return $should_be_set === isset($value);
        }
        if (isset($value)) {
            // We always want a case-insensitive match.
            if (!is_bool($value)) {
                $value = mb_strtolower($value);
            }
            return match ($condition['operator']) {
                '=' => $value == $condition['value'],
                '>' => $value > $condition['value'],
                '<' => $value < $condition['value'],
                '>=' => $value >= $condition['value'],
                '<=' => $value <= $condition['value'],
                '<>' => $value != $condition['value'],
                'IN' => array_search($value, $condition['value']) !== false,
                'NOT IN' => array_search($value, $condition['value']) === false,
                'STARTS_WITH' => str_starts_with($value, (string) $condition['value']),
                'CONTAINS' => str_contains($value, (string) $condition['value']),
                'ENDS_WITH' => str_ends_with($value, (string) $condition['value']),
                default => throw new Query_Exception('Invalid condition operator.'),
            };
        }
        return false;
    }
}