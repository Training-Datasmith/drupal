<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Core\Config\Entity\Importable_Entity_Storage_Interface;
use Drupal\Core\Config\Importer\Missing_Content_Event;
use Drupal\Core\Dependency_Injection\Dependency_Serialization_Trait;
use Drupal\Core\Entity\Entity_Storage_Exception;
use Drupal\Core\Site\Settings;
use Drupal\Core\String_Translation\String_Translation_Trait;
use Drupal\Core\String_Translation\Translation_Interface;
/**
 * Defines a configuration importer.
 *
 * A config importer imports the changes into the configuration system. To
 * determine which changes to import a StorageComparer in used.
 *
 * @see \Drupal\Core\Config\StorageComparerInterface
 *
 * The ConfigImporter has an identifier which is used to construct event names.
 * The events fired during an import are:
 * - ConfigEvents::IMPORT_VALIDATE: Events listening can throw a
 *   \Drupal\Core\Config\ConfigImporterException to prevent an import from
 *   occurring.
 *   @see \Drupal\Core\EventSubscriber\ConfigImportSubscriber
 * - ConfigEvents::IMPORT: Events listening can react to a successful import.
 *   @see \Drupal\Core\EventSubscriber\ConfigSnapshotSubscriber
 *
 * @see \Drupal\Core\Config\ConfigImporterEvent
 */
class Config_Importer
{
    use String_Translation_Trait;
    use Dependency_Serialization_Trait;
    /**
     * The name used to identify the lock.
     */
    public const LOCK_NAME = 'config_importer';
    /**
     * List of configuration file changes processed by the import().
     *
     * @var array
     */
    protected $processed_configuration;
    /**
     * List of extension changes processed by the import().
     *
     * @var array
     */
    protected $processed_extensions;
    /**
     * List of extension changes to be processed by the import().
     *
     * @var array
     */
    protected $extension_changelist;
    /**
     * Indicates changes to import have been validated.
     *
     * @var bool
     */
    protected $validated;
    /**
     * Indicates if a system theme is in processing theme install and uninstalls.
     *
     * @var bool
     */
    protected $processed_system_theme = false;
    /**
     * A log of any errors encountered.
     *
     * If errors are logged during the validation event the configuration
     * synchronization will not occur. If errors occur during an import then best
     * efforts are made to complete the synchronization.
     *
     * @var array
     */
    protected $errors = [];
    /**
     * The total number of extensions to process.
     *
     * @var int
     */
    protected $total_extensions_to_process = 0;
    /**
     * The total number of configuration objects to process.
     *
     * @var int
     */
    protected $total_configuration_to_process = 0;
    /**
     * Constructs a configuration import object.
     *
     * @param \Drupal\Core\Config\StorageComparerInterface $storageComparer
     *   A storage comparer object used to determine configuration changes and
     *   access the source and target storage objects.
     * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
     *   The event dispatcher used to notify subscribers of config import events.
     * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
     *   The configuration manager.
     * @param \Drupal\Core\Lock\LockBackendInterface $lock
     *   The lock backend to ensure multiple imports do not occur at the same
     *   time.
     * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
     *   The typed configuration manager.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
     *   The module handler.
     * @param \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller
     *   The module installer.
     * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
     *   The theme handler.
     * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
     *   The string translation service.
     * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
     *   The module extension list.
     * @param \Drupal\Core\Extension\ThemeExtensionList $themeExtensionList
     *   The theme extension list.
     */
    public function __construct(protected \Drupal\Core\Config\Storage_Comparer_Interface $storage_comparer, protected \Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface $event_dispatcher, protected \Drupal\Core\Config\Config_Manager_Interface $config_manager, protected \Drupal\Core\Lock\Lock_Backend_Interface $lock, protected \Drupal\Core\Config\Typed_Config_Manager_Interface $typed_config_manager, protected \Drupal\Core\Extension\Module_Handler_Interface $module_handler, protected \Drupal\Core\Extension\Module_Installer_Interface $module_installer, protected \Drupal\Core\Extension\Theme_Handler_Interface $theme_handler, Translation_Interface $string_translation, protected \Drupal\Core\Extension\Module_Extension_List $module_extension_list, protected \Drupal\Core\Extension\Theme_Extension_List $theme_extension_list)
    {
        $this->string_translation = $string_translation;
        foreach ($this->storage_comparer->get_all_collection_names() as $collection) {
            $this->processed_configuration[$collection] = $this->storage_comparer->get_empty_changelist();
        }
        $this->processed_extensions = $this->get_empty_extensions_processed_list();
    }
    /**
     * Logs an error message.
     *
     * @param string $message
     *   The message to log.
     */
    public function log_error($message): void
    {
        $this->errors[] = $message;
    }
    /**
     * Returns error messages created while running the import.
     *
     * @return array
     *   List of messages.
     */
    public function get_errors()
    {
        return $this->errors;
    }
    /**
     * Gets the configuration storage comparer.
     *
     * @return \Drupal\Core\Config\StorageComparerInterface
     *   Storage comparer object used to calculate configuration changes.
     */
    public function get_storage_comparer()
    {
        return $this->storage_comparer;
    }
    /**
     * Resets the storage comparer and processed list.
     *
     * @return $this
     *   The ConfigImporter instance.
     */
    public function reset(): static
    {
        $this->storage_comparer->reset();
        // Empty all the lists.
        foreach ($this->storage_comparer->get_all_collection_names() as $collection) {
            $this->processed_configuration[$collection] = $this->storage_comparer->get_empty_changelist();
        }
        $this->extension_changelist = $this->processed_extensions = $this->get_empty_extensions_processed_list();
        $this->validated = false;
        $this->processed_system_theme = false;
        return $this;
    }
    /**
     * Gets an empty list of extensions to process.
     *
     * @return array
     *   An empty list of extensions to process.
     */
    protected function get_empty_extensions_processed_list(): array
    {
        return ['module' => ['install' => [], 'uninstall' => []], 'theme' => ['install' => [], 'uninstall' => []]];
    }
    /**
     * Checks if there are any unprocessed configuration changes.
     *
     * @return bool
     *   TRUE if there are changes to process and FALSE if not.
     */
    public function has_unprocessed_configuration_changes(): bool
    {
        foreach ($this->storage_comparer->get_all_collection_names() as $collection) {
            foreach (['delete', 'create', 'rename', 'update'] as $op) {
                if (count($this->get_unprocessed_configuration($op, $collection))) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Gets list of processed changes.
     *
     * @param string $collection
     *   (optional) The configuration collection to get processed changes for.
     *   Defaults to the default collection.
     *
     * @return array
     *   An array containing a list of processed changes.
     */
    public function get_processed_configuration($collection = Storage_Interface::DEFAULT_COLLECTION)
    {
        return $this->processed_configuration[$collection];
    }
    /**
     * Sets a change as processed.
     *
     * @param string $collection
     *   The configuration collection to set a change as processed for.
     * @param string $op
     *   The change operation performed, either delete, create, rename, or update.
     * @param string $name
     *   The name of the configuration processed.
     */
    protected function set_processed_configuration($collection, $op, $name)
    {
        $this->processed_configuration[$collection][$op][] = $name;
    }
    /**
     * Gets a list of unprocessed changes for a given operation.
     *
     * @param string $op
     *   The change operation to get the unprocessed list for, either delete,
     *   create, rename, or update.
     * @param string $collection
     *   (optional) The configuration collection to get unprocessed changes for.
     *   Defaults to the default collection.
     *
     * @return array
     *   An array of configuration names.
     */
    public function get_unprocessed_configuration($op, $collection = Storage_Interface::DEFAULT_COLLECTION): array
    {
        return array_diff($this->storage_comparer->get_changelist($op, $collection), $this->processed_configuration[$collection][$op]);
    }
    /**
     * Gets list of processed extension changes.
     *
     * @return array
     *   An array containing a list of processed extension changes.
     */
    public function get_processed_extensions()
    {
        return $this->processed_extensions;
    }
    /**
     * Sets an extension change as processed.
     *
     * @param string $type
     *   The type of extension, either 'theme' or 'module'.
     * @param string $op
     *   The change operation performed, either install or uninstall.
     * @param string|array $name
     *   The name or names of the extension(s) processed.
     */
    protected function set_processed_extension($type, $op, $name)
    {
        $name = (array) $name;
        $this->processed_extensions[$type][$op] = array_merge($this->processed_extensions[$type][$op], $name);
    }
    /**
     * Populates the extension change list.
     */
    protected function create_extension_changelist()
    {
        // Create an empty changelist.
        $this->extension_changelist = $this->get_empty_extensions_processed_list();
        // Read the extensions information to determine changes.
        $current_extensions = $this->storage_comparer->get_target_storage()->read('core.extension');
        $new_extensions = $this->storage_comparer->get_source_storage()->read('core.extension');
        // If there is no extension information in sync then exit. This is probably
        // due to an empty sync directory.
        if (!$new_extensions) {
            return;
        }
        // Reset the module list in case a stale cache item has been set by another
        // process during deployment.
        $this->module_extension_list->reset();
        // Get a list of modules with dependency weights as values.
        $module_data = $this->module_extension_list->get_list();
        // Use the actual module weights.
        $module_list = array_combine(array_keys($module_data), array_keys($module_data));
        $module_list = array_map(fn(int|string $module) => $module_data[$module]->sort, $module_list);
        // Determine which modules to uninstall.
        $uninstall = array_keys(array_diff_key($current_extensions['module'], $new_extensions['module']));
        // Sort the list of newly uninstalled extensions by their weights, so that
        // dependencies are uninstalled last. Extensions of the same weight are
        // sorted in reverse alphabetical order, to ensure the order is exactly
        // opposite from installation. For example, this module list:
        // @code
        // [
        //   'actions' => 0,
        //   'block' => 0,
        //   'options' => -2,
        //   'text' => -1,
        // ];
        // @endcode
        // Will result in the following sort order:
        // 1. -2   options
        // 2. -1   text
        // 3.  0 0 block
        // 4.  0 1 actions
        // @todo Move this sorting functionality to the extension system.
        array_multisort(array_values($module_list), SORT_ASC, array_keys($module_list), SORT_DESC, $module_list);
        $this->extension_changelist['module']['uninstall'] = array_intersect(array_keys($module_list), $uninstall);
        // Determine which modules to install.
        $install = array_keys(array_diff_key($new_extensions['module'], $current_extensions['module']));
        // Always install required modules first. Respect the dependencies between
        // the modules.
        $install_required = [];
        $install_non_required = [];
        foreach ($install as $module) {
            if (!isset($module_data[$module])) {
                // The module doesn't exist. This is handled in
                // \Drupal\Core\EventSubscriber\ConfigImportSubscriber::validateModules().
                continue;
            }
            if (!empty($module_data[$module]->info['required'])) {
                $install_required[$module] = $module_data[$module]->sort;
            } else {
                $install_non_required[$module] = $module_data[$module]->sort;
            }
        }
        // Ensure that installed modules are sorted in exactly the reverse order
        // (with dependencies installed first, and modules of the same weight sorted
        // in alphabetical order).
        arsort($install_required);
        arsort($install_non_required);
        $this->extension_changelist['module']['install'] = array_keys($install_required + $install_non_required);
        // If we're installing the install profile ensure it comes last in the
        // list of modules to be installed. This will occur when installing a site
        // from configuration.
        if (isset($new_extensions['profile'])) {
            $install_profile_key = array_search($new_extensions['profile'], $this->extension_changelist['module']['install'], true);
            // If the profile is not in the list of modules to be installed this will
            // generate a validation error. See
            // \Drupal\Core\EventSubscriber\ConfigImportSubscriber::validateModules().
            if ($install_profile_key !== false) {
                unset($this->extension_changelist['module']['install'][$install_profile_key]);
                $this->extension_changelist['module']['install'][] = $new_extensions['profile'];
            }
        }
        // Get a list of themes with dependency weights as values.
        $theme_data = $this->theme_extension_list->get_list();
        // Use the actual theme weights.
        $theme_list = array_combine(array_keys($theme_data), array_keys($theme_data));
        $theme_list = array_map(fn(int|string $theme) => $theme_data[$theme]->sort, $theme_list);
        array_multisort(array_values($theme_list), SORT_ASC, array_keys($theme_list), SORT_DESC, $theme_list);
        // Work out what themes to install and to uninstall.
        $uninstall = array_keys(array_diff_key($current_extensions['theme'], $new_extensions['theme']));
        $this->extension_changelist['theme']['uninstall'] = array_intersect(array_keys($theme_list), $uninstall);
        // Ensure that installed themes are sorted in exactly the reverse order
        // (with dependencies installed first, and themes of the same weight sorted
        // in alphabetical order).
        $install = array_keys(array_diff_key($new_extensions['theme'], $current_extensions['theme']));
        $theme_list = array_reverse($theme_list);
        $this->extension_changelist['theme']['install'] = array_intersect(array_keys($theme_list), $install);
    }
    /**
     * Gets a list changes for extensions.
     *
     * @param string $type
     *   The type of extension, either 'theme' or 'module'.
     * @param string $op
     *   The change operation to get the unprocessed list for, either install
     *   or uninstall.
     *
     * @return array
     *   An array of extension names.
     */
    public function get_extension_changelist($type, $op = null)
    {
        if ($op) {
            return $this->extension_changelist[$type][$op];
        }
        return $this->extension_changelist[$type];
    }
    /**
     * Gets a list of unprocessed changes for extensions.
     *
     * @param string $type
     *   The type of extension, either 'theme' or 'module'.
     *
     * @return array
     *   An array of extension names.
     */
    protected function get_unprocessed_extensions($type): array
    {
        $changelist = $this->get_extension_changelist($type);
        return ['install' => array_diff($changelist['install'], $this->processed_extensions[$type]['install']), 'uninstall' => array_diff($changelist['uninstall'], $this->processed_extensions[$type]['uninstall'])];
    }
    /**
     * Imports the changelist to the target storage.
     *
     * @return $this
     *   The ConfigImporter instance.
     *
     * @throws \Drupal\Core\Config\ConfigException
     */
    public function import(): static
    {
        if ($this->has_unprocessed_configuration_changes()) {
            $sync_steps = $this->initialize();
            foreach ($sync_steps as $step) {
                $context = [];
                do {
                    $this->do_sync_step($step, $context);
                } while ($context['finished'] < 1);
            }
        }
        return $this;
    }
    /**
     * Calls a config import step.
     *
     * @param string|callable $sync_step
     *   The step to do. Either a method on the ConfigImporter class or a
     *   callable.
     * @param array $context
     *   A batch context array. If the config importer is not running in a batch
     *   the only array key that is used is $context['finished']. A process needs
     *   to set $context['finished'] = 1 when it is done.
     *
     * @throws \InvalidArgumentException
     *   Exception thrown if the $sync_step can not be called.
     */
    public function do_sync_step($sync_step, &$context): void
    {
        if ($this->validated) {
            $this->storage_comparer->write_mode();
        }
        if (is_string($sync_step) && method_exists($this, $sync_step)) {
            \Drupal::service('config.installer')->set_syncing(true);
            $this->{$sync_step}($context);
        } elseif (is_callable($sync_step)) {
            \Drupal::service('config.installer')->set_syncing(true);
            $sync_step($context, $this);
        } else {
            throw new \InvalidArgumentException('Invalid configuration synchronization step');
        }
        \Drupal::service('config.installer')->set_syncing(false);
    }
    /**
     * Initializes the config importer in preparation for processing a batch.
     *
     * @return array
     *   An array of \Drupal\Core\Config\ConfigImporter method names and callables
     *   that are invoked to complete the import. If there are modules or themes
     *   to process then an extra step is added.
     *
     * @throws \Drupal\Core\Config\ConfigImporterException
     *   If the configuration is already importing.
     */
    public function initialize()
    {
        // Ensure that the changes have been validated.
        $this->validate();
        if (!$this->lock->acquire(static::LOCK_NAME)) {
            // Another process is synchronizing configuration.
            throw new Config_Importer_Exception(sprintf('%s is already importing', static::LOCK_NAME));
        }
        $sync_steps = [];
        $modules = $this->get_unprocessed_extensions('module');
        foreach (['install', 'uninstall'] as $op) {
            $this->total_extensions_to_process += count($modules[$op]);
        }
        $themes = $this->get_unprocessed_extensions('theme');
        foreach (['install', 'uninstall'] as $op) {
            $this->total_extensions_to_process += count($themes[$op]);
        }
        // We have extensions to process.
        if ($this->total_extensions_to_process > 0) {
            $sync_steps[] = 'processExtensions';
        }
        $sync_steps[] = 'processConfigurations';
        $sync_steps[] = 'processMissingContent';
        // Allow modules to add new steps to configuration synchronization.
        $this->module_handler->alter('config_import_steps', $sync_steps, $this);
        $sync_steps[] = 'finish';
        return $sync_steps;
    }
    /**
     * Processes extensions as a batch operation.
     *
     * @param array|\ArrayAccess $context
     *   The batch context.
     */
    protected function process_extensions(&$context)
    {
        $operation = $this->get_next_extension_operation();
        if (!empty($operation)) {
            $this->process_extension($operation['type'], $operation['op'], $operation['name']);
            $names = implode(', ', (array) $operation['name']);
            $context['message'] = match ($operation['op']) {
                'install' => $this->t('Synchronizing extensions: installed @name.', ['@name' => $names]),
                'uninstall' => $this->t('Synchronizing extensions: uninstalled @name.', ['@name' => $names]),
            };
            $processed_count = count($this->processed_extensions['module']['install']) + count($this->processed_extensions['module']['uninstall']);
            $processed_count += count($this->processed_extensions['theme']['uninstall']) + count($this->processed_extensions['theme']['install']);
            $context['finished'] = $processed_count / $this->total_extensions_to_process;
        } else {
            $context['finished'] = 1;
        }
    }
    /**
     * Processes configuration as a batch operation.
     *
     * @param array|\ArrayAccess $context
     *   The batch context.
     */
    protected function process_configurations(&$context)
    {
        // The first time this is called we need to calculate the total to process.
        // This involves recalculating the changelist which will ensure that if
        // extensions have been processed any configuration affected will be taken
        // into account.
        if ($this->total_configuration_to_process == 0) {
            $this->storage_comparer->reset();
            foreach ($this->storage_comparer->get_all_collection_names() as $collection) {
                foreach (['delete', 'create', 'rename', 'update'] as $op) {
                    $this->total_configuration_to_process += count($this->get_unprocessed_configuration($op, $collection));
                }
            }
            // Adjust the totals for system.theme.
            // @see \Drupal\Core\Config\ConfigImporter::processExtension
            if ($this->processed_system_theme) {
                $this->total_configuration_to_process++;
            }
        }
        $operation = $this->get_next_configuration_operation();
        if (!empty($operation)) {
            if ($this->check_op($operation['collection'], $operation['op'], $operation['name'])) {
                $this->process_configuration($operation['collection'], $operation['op'], $operation['name']);
            }
            if ($operation['collection'] == Storage_Interface::DEFAULT_COLLECTION) {
                $context['message'] = $this->t('Synchronizing configuration: @op @name.', ['@op' => $operation['op'], '@name' => $operation['name']]);
            } else {
                $context['message'] = $this->t('Synchronizing configuration: @op @name in @collection.', ['@op' => $operation['op'], '@name' => $operation['name'], '@collection' => $operation['collection']]);
            }
            $processed_count = 0;
            foreach ($this->storage_comparer->get_all_collection_names() as $collection) {
                foreach (['delete', 'create', 'rename', 'update'] as $op) {
                    $processed_count += count($this->processed_configuration[$collection][$op]);
                }
            }
            $context['finished'] = $processed_count / $this->total_configuration_to_process;
        } else {
            $context['finished'] = 1;
        }
    }
    /**
     * Handles processing of missing content.
     *
     * @param array|\ArrayAccess $context
     *   Standard batch context.
     */
    protected function process_missing_content(&$context)
    {
        $sandbox =& $context['sandbox']['config'];
        if (!isset($sandbox['missing_content'])) {
            $missing_content = $this->config_manager->find_missing_content_dependencies();
            $sandbox['missing_content']['data'] = $missing_content;
            $sandbox['missing_content']['total'] = count($missing_content);
        } else {
            $missing_content = $sandbox['missing_content']['data'];
        }
        if (!empty($missing_content)) {
            $event = new Missing_Content_Event($missing_content);
            // Fire an event to allow listeners to create the missing content.
            $this->event_dispatcher->dispatch($event, Config_Events::IMPORT_MISSING_CONTENT);
            $sandbox['missing_content']['data'] = $event->get_missing_content();
        }
        $current_count = count($sandbox['missing_content']['data']);
        if ($current_count) {
            $context['message'] = $this->t('Resolving missing content');
            $context['finished'] = ($sandbox['missing_content']['total'] - $current_count) / $sandbox['missing_content']['total'];
        } else {
            $context['finished'] = 1;
        }
    }
    /**
     * Finishes the batch.
     *
     * @param array|\ArrayAccess $context
     *   The batch context.
     */
    protected function finish(&$context)
    {
        $this->event_dispatcher->dispatch(new Config_Importer_Event($this), Config_Events::IMPORT);
        // The import is now complete.
        $this->lock->release(static::LOCK_NAME);
        $this->reset();
        $context['message'] = $this->t('Finalizing configuration synchronization.');
        $context['finished'] = 1;
    }
    /**
     * Gets the next extension operation to perform.
     *
     * Uninstalls are processed first with themes coming before modules. Then
     * installs are processed with modules coming before themes. This order is
     * necessary because themes can depend on modules.
     *
     * @return array|false
     *   An array containing the next operation and extension name to perform it
     *   on. If there is nothing left to do returns FALSE;
     */
    protected function get_next_extension_operation(): array|false
    {
        foreach (['uninstall', 'install'] as $op) {
            $types = $op === 'uninstall' ? ['theme', 'module'] : ['module', 'theme'];
            foreach ($types as $type) {
                $unprocessed = $this->get_unprocessed_extensions($type);
                if (!empty($unprocessed[$op])) {
                    if ($type === 'module' && $op === 'install') {
                        $name = array_slice($unprocessed[$op], 0, Settings::get('core.multi_module_install_batch_size', 20));
                    } else {
                        $name = array_shift($unprocessed[$op]);
                    }
                    return ['op' => $op, 'type' => $type, 'name' => $name];
                }
            }
        }
        return false;
    }
    /**
     * Gets the next configuration operation to perform.
     *
     * @return array|false
     *   An array containing the next operation and configuration name to perform
     *   it on. If there is nothing left to do returns FALSE;
     */
    protected function get_next_configuration_operation(): array|false
    {
        // The order configuration operations is processed is important. Deletes
        // have to come first so that recreates can work.
        foreach ($this->storage_comparer->get_all_collection_names() as $collection) {
            foreach (['delete', 'create', 'rename', 'update'] as $op) {
                $config_names = $this->get_unprocessed_configuration($op, $collection);
                if (!empty($config_names)) {
                    return ['op' => $op, 'name' => array_shift($config_names), 'collection' => $collection];
                }
            }
        }
        return false;
    }
    /**
     * Dispatches validate event for a ConfigImporter object.
     *
     * Events should throw a \Drupal\Core\Config\ConfigImporterException to
     * prevent an import from occurring.
     *
     * @throws \Drupal\Core\Config\ConfigImporterException
     *   Exception thrown if the validate event logged any errors.
     */
    public function validate(): static
    {
        if (!$this->validated) {
            $this->errors = [];
            // Create the list of installs and uninstalls.
            $this->create_extension_changelist();
            // Validate renames.
            foreach ($this->get_unprocessed_configuration('rename') as $name) {
                $names = $this->storage_comparer->extract_rename_names($name);
                $old_entity_type_id = $this->config_manager->get_entity_type_id_by_name($names['old_name']);
                $new_entity_type_id = $this->config_manager->get_entity_type_id_by_name($names['new_name']);
                if ($old_entity_type_id != $new_entity_type_id) {
                    $this->log_error($this->t('Entity type mismatch on rename. @old_type not equal to @new_type for existing configuration @old_name and staged configuration @new_name.', ['@old_type' => $old_entity_type_id, '@new_type' => $new_entity_type_id, '@old_name' => $names['old_name'], '@new_name' => $names['new_name']]));
                }
                // Has to be a configuration entity.
                if (!$old_entity_type_id) {
                    $this->log_error($this->t('Rename operation for simple configuration. Existing configuration @old_name and staged configuration @new_name.', ['@old_name' => $names['old_name'], '@new_name' => $names['new_name']]));
                }
            }
            $this->event_dispatcher->dispatch(new Config_Importer_Event($this), Config_Events::IMPORT_VALIDATE);
            if (count($this->get_errors())) {
                $errors = array_merge(['There were errors validating the config synchronization.'], $this->get_errors());
                throw new Config_Importer_Exception(implode(PHP_EOL, $errors));
            }
            $this->validated = true;
        }
        return $this;
    }
    /**
     * Processes a configuration change.
     *
     * @param string $collection
     *   The configuration collection to process changes for.
     * @param string $op
     *   The change operation.
     * @param string $name
     *   The name of the configuration to process.
     *
     * @throws \Exception
     *   Thrown when the import process fails, only thrown when no importer log is
     *   set, otherwise the exception message is logged and the configuration
     *   is skipped.
     */
    protected function process_configuration($collection, $op, $name)
    {
        try {
            $processed = false;
            if ($collection == Storage_Interface::DEFAULT_COLLECTION) {
                $processed = $this->import_invoke_owner($collection, $op, $name);
            }
            if (!$processed) {
                $this->import_config($collection, $op, $name);
            }
        } catch (\Exception $e) {
            $this->log_error($this->t('Unexpected error during import with operation @op for @name: @message', ['@op' => $op, '@name' => $name, '@message' => $e->get_message()]));
            // Error for that operation was logged, mark it as processed so that
            // the import can continue.
            $this->set_processed_configuration($collection, $op, $name);
        }
    }
    /**
     * Processes an extension change.
     *
     * @param string $type
     *   The type of extension, either 'module' or 'theme'.
     * @param string $op
     *   The change operation.
     * @param string|array $names
     *   The name or names of the extension(s) to process.
     */
    protected function process_extension(string $type, string $op, string|array $names): void
    {
        $names = (array) $names;
        // Set the config installer to use the sync directory instead of the
        // extensions own default config directories.
        \Drupal::service('config.installer')->set_source_storage($this->storage_comparer->get_source_storage());
        if ($type == 'module') {
            $this->module_installer->{$op}($names, false);
            // Installing a module can cause a kernel boot therefore inject all the
            // services again.
            $this->re_inject_me();
            // During a module install or uninstall the container is rebuilt and the
            // module handler is called. This causes the container's instance of the
            // module handler not to have loaded all the enabled modules.
            $this->module_handler->load_all();
        }
        if ($type == 'theme') {
            // Theme uninstalls possible remove default or admin themes therefore we
            // need to import this before doing any. If there are no uninstalls and
            // the default or admin theme is changing this will be picked up whilst
            // processing configuration.
            if ($op == 'uninstall' && $this->processed_system_theme === false) {
                $this->import_config(Storage_Interface::DEFAULT_COLLECTION, 'update', 'system.theme');
                $this->config_manager->get_config_factory()->reset('system.theme');
                $this->processed_system_theme = true;
            }
            \Drupal::service('theme_installer')->{$op}($names);
            // Installing a theme can also cause a kernel boot, so re-inject services
            // as is done with modules.
            $this->re_inject_me();
        }
        $this->set_processed_extension($type, $op, $names);
    }
    /**
     * Checks that the operation is still valid.
     *
     * During a configuration import secondary writes and deletes are possible.
     * This method checks that the operation is still valid before processing a
     * configuration change.
     *
     * @param string $collection
     *   The configuration collection.
     * @param string $op
     *   The change operation.
     * @param string $name
     *   The name of the configuration to process.
     *
     * @return bool
     *   TRUE is to continue processing, FALSE otherwise.
     *
     * @throws \Drupal\Core\Config\ConfigImporterException
     */
    protected function check_op($collection, $op, $name): bool
    {
        if ($op == 'rename') {
            $names = $this->storage_comparer->extract_rename_names($name);
            $target_exists = $this->storage_comparer->get_target_storage($collection)->exists($names['new_name']);
            if ($target_exists) {
                // If the target exists, the rename has already occurred as the
                // result of a secondary configuration write. Change the operation
                // into an update. This is the desired behavior since renames often
                // have to occur together. For example, renaming a node type must
                // also result in renaming its fields and entity displays.
                $this->storage_comparer->move_rename_to_update($name);
                return false;
            }
            return true;
        }
        $target_exists = $this->storage_comparer->get_target_storage($collection)->exists($name);
        switch ($op) {
            case 'delete':
                if (!$target_exists) {
                    // The configuration has already been deleted. For example, a field
                    // is automatically deleted if all the instances are.
                    $this->set_processed_configuration($collection, $op, $name);
                    return false;
                }
                break;
            case 'create':
                if ($target_exists) {
                    // If the target already exists, use the entity storage to delete it
                    // again, if is a simple config, delete it directly.
                    if ($entity_type_id = $this->config_manager->get_entity_type_id_by_name($name)) {
                        $entity_storage = $this->config_manager->get_entity_type_manager()->get_storage($entity_type_id);
                        $entity_type = $this->config_manager->get_entity_type_manager()->get_definition($entity_type_id);
                        $entity = $entity_storage->load($entity_storage->get_id_from_config_name($name, $entity_type->get_config_prefix()));
                        $entity->delete();
                        $this->log_error($this->t('Deleted and replaced configuration entity "@name"', ['@name' => $name]));
                    } else {
                        $this->storage_comparer->get_target_storage($collection)->delete($name);
                        $this->log_error($this->t('Deleted and replaced configuration "@name"', ['@name' => $name]));
                    }
                    return true;
                }
                break;
            case 'update':
                if (!$target_exists) {
                    $this->log_error($this->t('Update target "@name" is missing.', ['@name' => $name]));
                    // Mark as processed so that the synchronization continues. Once the
                    // the current synchronization is complete it will show up as a
                    // create.
                    $this->set_processed_configuration($collection, $op, $name);
                    return false;
                }
                break;
        }
        return true;
    }
    /**
     * Writes a configuration change from the source to the target storage.
     *
     * @param string $collection
     *   The configuration collection.
     * @param string $op
     *   The change operation.
     * @param string $name
     *   The name of the configuration to process.
     */
    protected function import_config($collection, $op, $name)
    {
        // Allow config factory overriders to use a custom configuration object if
        // they are responsible for the collection.
        $overrider = $this->config_manager->get_config_collection_info()->get_override_service($collection);
        if ($overrider) {
            $config = $overrider->create_config_object($name, $collection);
        } else {
            $config = new Config($name, $this->storage_comparer->get_target_storage($collection), $this->event_dispatcher, $this->typed_config_manager);
        }
        if ($old_data = $this->storage_comparer->get_target_storage($collection)->read($name)) {
            $config->init_with_data($old_data);
        }
        if ($op == 'delete') {
            $config->delete();
        } else {
            $data = $this->storage_comparer->get_source_storage($collection)->read($name);
            $config->set_data($data ?: []);
            $config->save();
        }
        $this->set_processed_configuration($collection, $op, $name);
    }
    /**
     * Invokes import* methods on configuration entity storage.
     *
     * Allow modules to take over configuration change operations for higher-level
     * configuration data.
     *
     * @todo Add support for other extension types; e.g., themes etc.
     *
     * @param string $collection
     *   The configuration collection.
     * @param string $op
     *   The change operation to get the unprocessed list for, either delete,
     *   create, rename, or update.
     * @param string $name
     *   The name of the configuration to process.
     *
     * @return bool
     *   TRUE if the configuration was imported as a configuration entity. FALSE
     *   otherwise.
     *
     * @throws \Drupal\Core\Entity\EntityStorageException
     *   Thrown if the data is owned by an entity type, but the entity storage
     *   does not support imports.
     */
    protected function import_invoke_owner($collection, $op, $name)
    {
        // Renames are handled separately.
        if ($op == 'rename') {
            return $this->import_invoke_rename($collection, $name);
        }
        // Validate the configuration object name before importing it.
        // Config::validateName($name);
        if ($entity_type = $this->config_manager->get_entity_type_id_by_name($name)) {
            $old_config = new Config($name, $this->storage_comparer->get_target_storage($collection), $this->event_dispatcher, $this->typed_config_manager);
            if ($old_data = $this->storage_comparer->get_target_storage($collection)->read($name)) {
                $old_config->init_with_data($old_data);
            }
            $data = $this->storage_comparer->get_source_storage($collection)->read($name);
            $new_config = new Config($name, $this->storage_comparer->get_target_storage($collection), $this->event_dispatcher, $this->typed_config_manager);
            if ($data !== false) {
                $new_config->set_data($data);
            }
            $method = 'import' . ucfirst($op);
            $entity_storage = $this->config_manager->get_entity_type_manager()->get_storage($entity_type);
            // Call to the configuration entity's storage to handle the configuration
            // change.
            if (!$entity_storage instanceof Importable_Entity_Storage_Interface) {
                throw new Entity_Storage_Exception(sprintf('The entity storage "%s" for the "%s" entity type does not support imports', $entity_storage::class, $entity_type));
            }
            $entity_storage->{$method}($name, $new_config, $old_config);
            $this->set_processed_configuration($collection, $op, $name);
            return true;
        }
        return false;
    }
    /**
     * Imports a configuration entity rename.
     *
     * @param string $collection
     *   The configuration collection.
     * @param string $rename_name
     *   The rename configuration name, as provided by
     *   \Drupal\Core\Config\StorageComparer::createRenameName().
     *
     * @return bool
     *   TRUE if the configuration was imported as a configuration entity. FALSE
     *   otherwise.
     *
     * @throws \Drupal\Core\Entity\EntityStorageException
     *   Thrown if the data is owned by an entity type, but the entity storage
     *   does not support imports.
     *
     * @see \Drupal\Core\Config\ConfigImporter::createRenameName()
     */
    protected function import_invoke_rename($collection, $rename_name): bool
    {
        $names = $this->storage_comparer->extract_rename_names($rename_name);
        $entity_type_id = $this->config_manager->get_entity_type_id_by_name($names['old_name']);
        $old_config = new Config($names['old_name'], $this->storage_comparer->get_target_storage($collection), $this->event_dispatcher, $this->typed_config_manager);
        if ($old_data = $this->storage_comparer->get_target_storage($collection)->read($names['old_name'])) {
            $old_config->init_with_data($old_data);
        }
        $data = $this->storage_comparer->get_source_storage($collection)->read($names['new_name']);
        $new_config = new Config($names['new_name'], $this->storage_comparer->get_target_storage($collection), $this->event_dispatcher, $this->typed_config_manager);
        if ($data !== false) {
            $new_config->set_data($data);
        }
        $entity_storage = $this->config_manager->get_entity_type_manager()->get_storage($entity_type_id);
        // Call to the configuration entity's storage to handle the configuration
        // change.
        if (!$entity_storage instanceof Importable_Entity_Storage_Interface) {
            throw new Entity_Storage_Exception(sprintf("The entity storage '%s' for the '%s' entity type does not support imports", $entity_storage::class, $entity_type_id));
        }
        $entity_storage->import_rename($names['old_name'], $new_config, $old_config);
        $this->set_processed_configuration($collection, 'rename', $rename_name);
        return true;
    }
    /**
     * Determines if an import is already running.
     *
     * @return bool
     *   TRUE if an import is already running, FALSE if not.
     */
    public function already_importing(): bool
    {
        return !$this->lock->lock_may_be_available(static::LOCK_NAME);
    }
    /**
     * Gets all the service dependencies from \Drupal.
     *
     * Since the ConfigImporter handles module installation the kernel and the
     * container can be rebuilt and altered during processing. It is necessary to
     * keep the services used by the importer in sync.
     */
    protected function re_inject_me()
    {
        // When rebuilding the container,
        // \Drupal\Core\DrupalKernel::initializeContainer() saves the hashes of the
        // old container and passes them to the new one. So __sleep() will
        // recognize the old services and then __wakeup() will restore them from
        // the new container.
        $this->__sleep();
        $this->__wakeup();
        $this->storage_comparer->__sleep();
        $this->storage_comparer->__wakeup();
    }
}