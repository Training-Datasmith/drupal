<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Schema;

use Drupal\Core\Config\Typed_Config_Manager_Interface;
use Drupal\Core\Typed_Data\Typed_Data;
use Drupal\Core\Typed_Data\Typed_Data_Manager_Interface;
/**
 * Defines a generic configuration element.
 */
abstract class Element extends Typed_Data
{
    /**
     * The configuration value.
     *
     * @var mixed
     */
    protected $value;
    /**
     * Gets the typed configuration manager.
     *
     * Overrides \Drupal\Core\TypedData\TypedDataTrait::getTypedDataManager() to
     * ensure the typed configuration manager is returned.
     *
     * @return \Drupal\Core\Config\TypedConfigManagerInterface
     *   The typed configuration manager.
     */
    public function get_typed_data_manager()
    {
        if (empty($this->typed_data_manager)) {
            $this->set_typed_data_manager(\Drupal::service('config.typed'));
        }
        return $this->typed_data_manager;
    }
    /**
     * Sets the typed config manager.
     *
     * Overrides \Drupal\Core\TypedData\TypedDataTrait::setTypedDataManager() to
     * ensure that only typed configuration manager can be used.
     *
     * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
     *   The typed config manager. This must be an instance of
     *   \Drupal\Core\Config\TypedConfigManagerInterface. If it is not, then this
     *   method will error when assertions are enabled. We can not narrow the
     *   type hint as this will cause PHP errors.
     *
     * @return $this
     */
    public function set_typed_data_manager(Typed_Data_Manager_Interface $typed_data_manager)
    {
        assert($typed_data_manager instanceof Typed_Config_Manager_Interface, '$typed_data_manager should be an instance of \Drupal\Core\Config\TypedConfigManagerInterface.');
        $this->typed_data_manager = $typed_data_manager;
        return $this;
    }
}