<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
/**
 * Matcher class to get autocompletion results for entity reference.
 */
class Entity_Autocomplete_Matcher implements Entity_Autocomplete_Matcher_Interface
{
    /**
     * Constructs an EntityAutocompleteMatcher object.
     *
     * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selectionManager
     *   The entity reference selection handler plugin manager.
     */
    public function __construct(protected \Drupal\Core\Entity\Entity_Reference_Selection\Selection_Plugin_Manager_Interface $selection_manager)
    {
    }
    /**
     * {@inheritdoc}
     * @return array{value: mixed, label: mixed}[]
     */
    public function get_matches($target_type, $selection_handler, $selection_settings, $string = ''): array
    {
        $matches = [];
        $options = $selection_settings + ['target_type' => $target_type, 'handler' => $selection_handler];
        $handler = $this->selection_manager->get_instance($options);
        if (isset($string)) {
            // Get an array of matching entities.
            $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
            $match_limit = isset($selection_settings['match_limit']) ? (int) $selection_settings['match_limit'] : 10;
            $entity_labels = $handler->get_referenceable_entities($string, $match_operator, $match_limit);
            // Loop through the entities and convert them into autocomplete output.
            foreach ($entity_labels as $values) {
                foreach ($values as $entity_id => $label) {
                    $key = "{$label} ({$entity_id})";
                    // Strip things like starting/trailing white spaces, line breaks and
                    // tags.
                    $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(Html::decode_entities(strip_tags($key)))));
                    // Names containing commas or quotes must be wrapped in quotes.
                    $key = Tags::encode($key);
                    $matches[] = ['value' => $key, 'label' => $label];
                }
            }
        }
        return $matches;
    }
}