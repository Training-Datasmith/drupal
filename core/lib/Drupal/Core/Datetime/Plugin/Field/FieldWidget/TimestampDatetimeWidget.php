<?php

declare (strict_types=1);
namespace Drupal\Core\Datetime\Plugin\Field\Field_Widget;

use Drupal\Core\Datetime\Drupal_Date_Time;
use Drupal\Core\Field\Attribute\Field_Widget;
use Drupal\Core\Field\Field_Item_List_Interface;
use Drupal\Core\Field\Widget_Base;
use Drupal\Core\Form\Form_State_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
/**
 * Plugin implementation of the 'datetime timestamp' widget.
 */
#[Field_Widget(id: 'datetime_timestamp', label: new Translatable_Markup('Datetime Timestamp'), field_types: ['timestamp', 'created'])]
class Timestamp_Datetime_Widget extends Widget_Base
{
    /**
     * {@inheritdoc}
     */
    public function form_element(Field_Item_List_Interface $items, $delta, array $element, array &$form, Form_State_Interface $form_state): array
    {
        $default_value = isset($items[$delta]->value) ? Drupal_Date_Time::create_from_timestamp($items[$delta]->value) : '';
        $element['value'] = $element + ['#type' => 'datetime', '#default_value' => $default_value, '#date_year_range' => '1902:2037'];
        $element['value']['#description'] = $element['#description'] !== '' ? $element['#description'] : $this->t('Leave blank to use the time of form submission.');
        return $element;
    }
    /**
     * {@inheritdoc}
     */
    public function massage_form_values(array $values, array $form, Form_State_Interface $form_state): array
    {
        foreach ($values as &$item) {
            // @todo The structure is different whether access is denied or not, to
            //   be fixed in https://www.drupal.org/node/2326533.
            if (isset($item['value']) && $item['value'] instanceof Drupal_Date_Time) {
                $date = $item['value'];
            } elseif (isset($item['value']['object']) && $item['value']['object'] instanceof Drupal_Date_Time) {
                $date = $item['value']['object'];
            } else {
                $date = new Drupal_Date_Time();
            }
            $item['value'] = $date->get_timestamp();
        }
        return $values;
    }
}