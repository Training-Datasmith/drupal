<?php

declare (strict_types=1);
namespace Drupal\Core\Datetime\Entity;

use Drupal\Core\Config\Entity\Config_Entity_Base;
use Drupal\Core\Config\Entity\Config_Entity_Interface;
use Drupal\Core\Datetime\Date_Format_Interface;
use Drupal\Core\Entity\Attribute\Config_Entity_Type;
use Drupal\Core\String_Translation\Translatable_Markup;
use Drupal\system\Date_Format_Access_Control_Handler;
/**
 * Defines the Date Format configuration entity class.
 */
#[Config_Entity_Type(id: 'date_format', label: new Translatable_Markup('Date format'), entity_keys: ['id' => 'id', 'label' => 'label'], handlers: ['access' => Date_Format_Access_Control_Handler::class], admin_permission: 'administer site configuration', list_cache_tags: ['rendered'], config_export: ['id', 'label', 'locked', 'pattern'])]
class Date_Format extends Config_Entity_Base implements Date_Format_Interface
{
    /**
     * The date format machine name.
     *
     * @var string
     */
    protected $id;
    /**
     * The human-readable name of the date format entity.
     *
     * @var string
     */
    protected $label;
    /**
     * The date format pattern.
     *
     * @var string
     */
    protected $pattern;
    /**
     * The locked status of this date format.
     *
     * @var bool
     */
    protected $locked = false;
    /**
     * {@inheritdoc}
     */
    public function get_pattern()
    {
        return $this->pattern;
    }
    /**
     * {@inheritdoc}
     */
    public function set_pattern($pattern): static
    {
        $this->pattern = $pattern;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function is_locked(): bool
    {
        return (bool) $this->locked;
    }
    /**
     * {@inheritdoc}
     */
    public static function sort(Config_Entity_Interface $a, Config_Entity_Interface $b): int
    {
        if ($a->is_locked() == $b->is_locked()) {
            $a_label = $a->label();
            $b_label = $b->label();
            return strnatcasecmp($a_label, $b_label);
        }
        return $a->is_locked() ? 1 : -1;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_tags_to_invalidate(): array
    {
        return ['rendered'];
    }
}