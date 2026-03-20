<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Core\Extension\Module_Extension_List;
use Drupal\Core\Extension\Module_Handler_Interface;
use Drupal\Core\Extension\Module_Installer_Interface;
use Drupal\Core\Extension\Theme_Extension_List;
use Drupal\Core\Extension\Theme_Handler_Interface;
use Drupal\Core\Lock\Lock_Backend_Interface;
use Drupal\Core\String_Translation\Translation_Interface;
use Symfony\Component\Dependency_Injection\Attribute\Autowire;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher_Interface;
/**
 * Factory class to create config importer objects.
 *
 * This class is declared as final because the ConfigImporter class is not
 * intended to be swappable.
 */
final class Config_Importer_Factory
{
    /**
     * Creates a ConfigImporterFactory instance.
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
     *   The event dispatcher service.
     * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
     *   The config manager.
     * @param \Drupal\Core\Lock\LockBackendInterface $lock
     *   The lock backend to prevent multiple imports occurring at the same time.
     * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
     *   The typed config manager.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
     *   The module handler.
     * @param \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller
     *   The module installer service.
     * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
     *   The theme handler service.
     * @param \Drupal\Core\StringTranslation\TranslationManager $stringTranslation
     *   The string translation service.
     * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
     *   The module extension list service.
     * @param \Drupal\Core\Extension\ThemeExtensionList $themeExtensionList
     *   The theme extension list service.
     */
    public function __construct(
        protected Event_Dispatcher_Interface $event_dispatcher,
        protected Config_Manager_Interface $config_manager,
        #[Autowire(service: 'lock.persistent')]
        protected Lock_Backend_Interface $lock,
        protected Typed_Config_Manager_Interface $typed_config_manager,
        protected Module_Handler_Interface $module_handler,
        protected Module_Installer_Interface $module_installer,
        protected Theme_Handler_Interface $theme_handler,
        protected Translation_Interface $string_translation,
        protected Module_Extension_List $module_extension_list,
        protected Theme_Extension_List $theme_extension_list
    )
    {
    }
    /**
     * Creates a ConfigImporter instance.
     *
     * @param \Drupal\Core\Config\StorageComparer $storage_comparer
     *   The storage comparer object. The type is the class and not
     *   StorageComparerInterface because that is due to be removed: see
     *   https://www.drupal.org/project/drupal/issues/3410037.
     *
     * @return \Drupal\Core\Config\ConfigImporter
     *   A config importer instance.
     */
    public function get(Storage_Comparer $storage_comparer): Config_Importer
    {
        return new Config_Importer($storage_comparer, $this->event_dispatcher, $this->config_manager, $this->lock, $this->typed_config_manager, $this->module_handler, $this->module_installer, $this->theme_handler, $this->string_translation, $this->module_extension_list, $this->theme_extension_list);
    }
}