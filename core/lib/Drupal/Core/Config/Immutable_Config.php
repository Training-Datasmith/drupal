<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

/**
 * Defines the immutable configuration object.
 *
 * Encapsulates all capabilities needed for runtime configuration handling
 * except being able to change the configuration.
 *
 * If you need to be able to change configuration use
 * \Drupal\Core\Form\ConfigFormBaseTrait or
 * \Drupal\Core\Config\ConfigFactoryInterface::getEditable().
 *
 * @see \Drupal\Core\Form\ConfigFormBaseTrait
 * @see \Drupal\Core\Config\ConfigFactoryInterface::getEditable()
 * @see \Drupal\Core\Config\ConfigFactoryInterface::get()
 *
 * @ingroup config_api
 */
class Immutable_Config extends Config
{
    /**
     * {@inheritdoc}
     */
    public function set($key, $value): never
    {
        throw new Immutable_Config_Exception("Can not set values on immutable configuration {$this->get_name()}:{$key}. Use \\Drupal\\Core\\Config\\ConfigFactoryInterface::getEditable() to retrieve a mutable configuration object");
    }
    /**
     * {@inheritdoc}
     */
    public function clear($key): never
    {
        throw new Immutable_Config_Exception("Can not clear {$key} key in immutable configuration {$this->get_name()}. Use \\Drupal\\Core\\Config\\ConfigFactoryInterface::getEditable() to retrieve a mutable configuration object");
    }
    /**
     * {@inheritdoc}
     */
    public function save($has_trusted_data = false): never
    {
        throw new Immutable_Config_Exception("Can not save immutable configuration {$this->get_name()}. Use \\Drupal\\Core\\Config\\ConfigFactoryInterface::getEditable() to retrieve a mutable configuration object");
    }
    /**
     * {@inheritdoc}
     */
    public function delete(): never
    {
        throw new Immutable_Config_Exception("Can not delete immutable configuration {$this->get_name()}. Use \\Drupal\\Core\\Config\\ConfigFactoryInterface::getEditable() to retrieve a mutable configuration object");
    }
}