<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Nested_Array;
use Drupal\Core\Config\Entity\Config_Dependency_Manager;
use Drupal\Core\Installer\Installer_Kernel;
/**
 * The config installer.
 */
class Config_Installer implements Config_Installer_Interface
{
    /**
     * The active configuration storages, keyed by collection.
     *
     * @var \Drupal\Core\Config\StorageInterface[]
     */
    protected $active_storages;
    /**
     * The configuration storage that provides the default configuration.
     *
     * @var \Drupal\Core\Config\StorageInterface
     */
    protected $source_storage;
    /**
     * Is configuration being created as part of a configuration sync.
     *
     * @var bool
     */
    protected $is_syncing = false;
    /**
     * Constructs the configuration installer.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
     *   The configuration factory.
     * @param \Drupal\Core\Config\StorageInterface $active_storage
     *   The active configuration storage.
     * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfig
     *   The typed configuration manager.
     * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
     *   The configuration manager.
     * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
     *   The event dispatcher.
     * @param string $installProfile
     *   The name of the currently active installation profile.
     * @param \Drupal\Core\Extension\ExtensionPathResolver $extensionPathResolver
     *   The extension path resolver.
     */
    public function __construct(
        protected \Drupal\Core\Config\Config_Factory_Interface $config_factory,
        Storage_Interface $active_storage,
        protected \Drupal\Core\Config\Typed_Config_Manager_Interface $typed_config,
        protected \Drupal\Core\Config\Config_Manager_Interface $config_manager,
        protected \Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface $event_dispatcher,
        /**
         * The name of the currently active installation profile.
         */
        protected $install_profile,
        protected \Drupal\Core\Extension\Extension_Path_Resolver $extension_path_resolver
    )
    {
        $this->active_storages[$active_storage->get_collection_name()] = $active_storage;
    }
    /**
     * {@inheritdoc}
     */
    public function install_default_config($type, $name, Default_Config_Mode $mode = Default_Config_Mode::All): void
    {
        $extension_path = $this->extension_path_resolver->get_path($type, $name);
        // Refresh the schema cache if the extension provides configuration schema
        // or is a theme.
        if (is_dir($extension_path . '/' . Install_Storage::CONFIG_SCHEMA_DIRECTORY) || $type == 'theme') {
            $this->typed_config->clear_cached_definitions();
        }
        if ($mode->create_install_config()) {
            $default_install_path = $this->get_default_config_directory($type, $name);
            if (is_dir($default_install_path)) {
                if (!$this->is_syncing()) {
                    $storage = new File_Storage($default_install_path, Storage_Interface::DEFAULT_COLLECTION);
                    $prefix = '';
                } else {
                    // The configuration importer sets the source storage on the config
                    // installer. The configuration importer handles all of the
                    // configuration entity imports. We only need to ensure that simple
                    // configuration is created when the extension is installed.
                    $storage = $this->get_source_storage();
                    $prefix = $name . '.';
                }
                // Gets profile storages to search for overrides if necessary.
                $profile_storages = $this->get_profile_storages($name);
                if ($mode === Default_Config_Mode::InstallEntities) {
                    // This is an optimization. If we're installing only config entities
                    // then we're only interested in the default collection.
                    $collections = [Storage_Interface::DEFAULT_COLLECTION];
                } else {
                    // Gather information about all the supported collections.
                    $collections = $this->config_manager->get_config_collection_info()->get_collection_names();
                }
                foreach ($collections as $collection) {
                    $config_to_create = $this->get_config_to_create($storage, $collection, $prefix, $profile_storages);
                    if ($collection === Storage_Interface::DEFAULT_COLLECTION && ($mode === Default_Config_Mode::InstallEntities || $mode === Default_Config_Mode::InstallSimple)) {
                        // Filter out config depending on the mode. The mode can be used to
                        // only install simple config or config entities.
                        $config_to_create = array_filter($config_to_create, function ($config_name) use ($mode): bool {
                            $is_config_entity = $this->config_manager->get_entity_type_id_by_name($config_name) !== null;
                            if ($is_config_entity) {
                                return $mode === Default_Config_Mode::InstallEntities;
                            }
                            return $mode === Default_Config_Mode::InstallSimple;
                        }, ARRAY_FILTER_USE_KEY);
                    }
                    if ($name === $this->drupal_get_profile()) {
                        // If we're installing a profile ensure simple configuration that
                        // already exists is excluded as it will have already been written.
                        // This means that if the configuration is changed by something else
                        // during the install it will not be overwritten again.
                        $existing_configuration = array_filter($this->get_active_storages($collection)->list_all(), fn($config_name) => !$this->config_manager->get_entity_type_id_by_name($config_name));
                        $config_to_create = array_diff_key($config_to_create, array_flip($existing_configuration));
                    }
                    if (!empty($config_to_create)) {
                        $this->create_configuration($collection, $config_to_create);
                    }
                }
            }
        }
        if ($mode->create_optional_config()) {
            // During a drupal installation optional configuration is installed at the
            // end of the installation process. Once the install profile is installed
            // optional configuration should be installed as usual.
            // @see install_install_profile()
            $profile_installed = in_array($this->drupal_get_profile(), $this->get_enabled_extensions(), true);
            if (!$this->is_syncing() && (!Installer_Kernel::installation_attempted() || $profile_installed)) {
                $optional_install_path = $extension_path . '/' . Install_Storage::CONFIG_OPTIONAL_DIRECTORY;
                if (is_dir($optional_install_path)) {
                    // Install any optional config the module provides.
                    $storage = new File_Storage($optional_install_path, Storage_Interface::DEFAULT_COLLECTION);
                    $this->install_optional_config($storage, '');
                }
            }
        }
        if ($mode->create_site_optional_config()) {
            // During a drupal installation optional configuration is installed at the
            // end of the installation process. Once the install profile is installed
            // optional configuration should be installed as usual.
            // @see install_install_profile()
            $profile_installed = in_array($this->drupal_get_profile(), $this->get_enabled_extensions(), true);
            if (!$this->is_syncing() && (!Installer_Kernel::installation_attempted() || $profile_installed)) {
                // Install any optional configuration entities whose dependencies can
                // now be met. This searches all the installed modules config/optional
                // directories.
                $storage = new Extension_Install_Storage($this->get_active_storages(Storage_Interface::DEFAULT_COLLECTION), Install_Storage::CONFIG_OPTIONAL_DIRECTORY, Storage_Interface::DEFAULT_COLLECTION, false, $this->install_profile);
                $this->install_optional_config($storage, [$type => $name]);
            }
        }
        // Reset all the static caches and list caches.
        $this->config_factory->reset();
    }
    /**
     * {@inheritdoc}
     */
    public function install_optional_config(?Storage_Interface $storage = null, $dependency = []): void
    {
        $profile = $this->drupal_get_profile();
        $enabled_extensions = $this->get_enabled_extensions();
        $existing_config = $this->get_active_storages()->list_all();
        // Create the storages to read configuration from.
        if (!$storage) {
            // Search the install profile's optional configuration too.
            $storage = new Extension_Install_Storage($this->get_active_storages(Storage_Interface::DEFAULT_COLLECTION), Install_Storage::CONFIG_OPTIONAL_DIRECTORY, Storage_Interface::DEFAULT_COLLECTION, true, $this->install_profile);
            // The extension install storage ensures that overrides are used.
            $profile_storage = null;
        } elseif (!empty($profile)) {
            // Creates a profile storage to search for overrides.
            $profile_install_path = $this->extension_path_resolver->get_path('module', $profile) . '/' . Install_Storage::CONFIG_OPTIONAL_DIRECTORY;
            $profile_storage = new File_Storage($profile_install_path, Storage_Interface::DEFAULT_COLLECTION);
        } else {
            // Profile has not been set yet. For example during the first steps of the
            // installer or during unit tests.
            $profile_storage = null;
        }
        // Build the list of possible configuration to create.
        $list = $storage->list_all();
        if ($profile_storage && !empty($dependency)) {
            // Only add the optional profile configuration into the list if we are
            // have a dependency to check. This ensures that optional profile
            // configuration is not unexpectedly re-created after being deleted.
            $list = array_unique(array_merge($list, $profile_storage->list_all()));
        }
        // Filter the list of configuration to only include configuration that
        // should be created.
        $list = array_filter(
            $list,
            // Only list configuration that:
            // - does not already exist
            // - is a configuration entity (this also excludes config that has an
            //   implicit dependency on modules that are not yet installed)
            fn($config_name) => !in_array($config_name, $existing_config) && $this->config_manager->get_entity_type_id_by_name($config_name)
        );
        $all_config = array_merge($existing_config, $list);
        $all_config = array_combine($all_config, $all_config);
        $config_to_create = $storage->read_multiple($list);
        // Check to see if the corresponding override storage has any overrides or
        // new configuration that can be installed.
        if ($profile_storage) {
            $config_to_create = $profile_storage->read_multiple($list) + $config_to_create;
        }
        // Sort $config_to_create in the order of the least dependent first.
        $dependency_manager = new Config_Dependency_Manager();
        $dependency_manager->set_data($config_to_create);
        $config_to_create = array_merge(array_flip($dependency_manager->sort_all()), $config_to_create);
        if (!empty($dependency)) {
            // In order to work out dependencies we need the full config graph.
            $dependency_manager->set_data($this->get_active_storages()->read_multiple($existing_config) + $config_to_create);
            $dependencies = $dependency_manager->get_dependent_entities(key($dependency), reset($dependency));
        }
        foreach ($config_to_create as $config_name => $data) {
            // Remove configuration where its dependencies cannot be met.
            $remove = !$this->validate_dependencies($config_name, $data, $enabled_extensions, $all_config);
            // Remove configuration that is not dependent on $dependency, if it is
            // defined.
            if (!$remove && !empty($dependency)) {
                $remove = !isset($dependencies[$config_name]);
            }
            if ($remove) {
                // Remove from the list of configuration to create.
                unset($config_to_create[$config_name]);
                // Remove from the list of all configuration. This ensures that any
                // configuration that depends on this configuration is also removed.
                unset($all_config[$config_name]);
            }
        }
        // Create the optional configuration if there is any left after filtering.
        if (!empty($config_to_create)) {
            $this->create_configuration(Storage_Interface::DEFAULT_COLLECTION, $config_to_create);
        }
    }
    /**
     * Gets configuration data from the provided storage to create.
     *
     * @param StorageInterface $storage
     *   The configuration storage to read configuration from.
     * @param string $collection
     *   The configuration collection to use.
     * @param string $prefix
     *   (optional) Limit to configuration starting with the provided string.
     * @param \Drupal\Core\Config\StorageInterface[] $profile_storages
     *   An array of storage interfaces containing profile configuration to check
     *   for overrides.
     *
     * @return array
     *   An array of configuration data read from the source storage keyed by the
     *   configuration object name.
     */
    protected function get_config_to_create(Storage_Interface $storage, $collection, $prefix = '', array $profile_storages = [])
    {
        if ($storage->get_collection_name() != $collection) {
            $storage = $storage->create_collection($collection);
        }
        $data = $storage->read_multiple($storage->list_all($prefix));
        // Check to see if configuration provided by the install profile has any
        // overrides.
        foreach ($profile_storages as $profile_storage) {
            if ($profile_storage->get_collection_name() != $collection) {
                $profile_storage = $profile_storage->create_collection($collection);
            }
            $profile_overrides = $profile_storage->read_multiple(array_keys($data));
            if (Installer_Kernel::installation_attempted()) {
                // During installation overrides of simple configuration are applied
                // immediately. Configuration entities that are overridden will be
                // updated when the profile is installed. This allows install profiles
                // to provide configuration entity overrides that have dependencies that
                // cannot be met when the module provided configuration entity is
                // created.
                foreach ($profile_overrides as $name => $override_data) {
                    // The only way to determine if they are configuration entities is the
                    // presence of a dependencies key.
                    if (!isset($override_data['dependencies'])) {
                        $data[$name] = $override_data;
                    }
                }
            } else {
                // Allow install profiles to provide overridden configuration for new
                // extensions that are being enabled after Drupal has already been
                // installed. This allows profiles to ship new extensions in version
                // updates without requiring additional code to apply the overrides.
                $data = $profile_overrides + $data;
            }
        }
        return $data;
    }
    /**
     * Creates configuration in a collection based on the provided list.
     *
     * @param string $collection
     *   The configuration collection.
     * @param array $config_to_create
     *   An array of configuration data to create, keyed by name.
     */
    protected function create_configuration($collection, array $config_to_create)
    {
        // Order the configuration to install in the order of dependencies.
        if ($collection == Storage_Interface::DEFAULT_COLLECTION) {
            $dependency_manager = new Config_Dependency_Manager();
            $config_names = $dependency_manager->set_data($config_to_create)->sort_all();
        } else {
            $config_names = array_keys($config_to_create);
        }
        foreach ($config_names as $name) {
            // Allow config factory overriders to use a custom configuration object if
            // they are responsible for the collection.
            $overrider = $this->config_manager->get_config_collection_info()->get_override_service($collection);
            if ($overrider) {
                $new_config = $overrider->create_config_object($name, $collection);
            } else {
                $new_config = new Config($name, $this->get_active_storages($collection), $this->event_dispatcher, $this->typed_config);
            }
            if ($config_to_create[$name] !== false) {
                // Add a hash to configuration created through the installer so it is
                // possible to know if the configuration was created by installing an
                // extension and to track which version of the default config was used.
                if (!$this->is_syncing() && $collection == Storage_Interface::DEFAULT_COLLECTION) {
                    $config_to_create[$name] = ['_core' => ['default_config_hash' => Crypt::hash_base64(serialize($config_to_create[$name]))]] + $config_to_create[$name];
                }
                $new_config->set_data($config_to_create[$name]);
            }
            if ($collection == Storage_Interface::DEFAULT_COLLECTION && $entity_type = $this->config_manager->get_entity_type_id_by_name($name)) {
                // If we are syncing do not create configuration entities. Pluggable
                // configuration entities can have dependencies on modules that are
                // not yet enabled. This approach means that any code that expects
                // default configuration entities to exist will be unstable after the
                // module has been enabled and before the config entity has been
                // imported.
                if ($this->is_syncing()) {
                    continue;
                }
                /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $entity_storage */
                $entity_storage = $this->config_manager->get_entity_type_manager()->get_storage($entity_type);
                $id = $entity_storage->get_id_from_config_name($name, $entity_storage->get_entity_type()->get_config_prefix());
                // It is possible that secondary writes can occur during configuration
                // creation. Updates of such configuration are allowed.
                if ($this->get_active_storages($collection)->exists($name)) {
                    $entity = $entity_storage->load($id);
                    $entity = $entity_storage->update_from_storage_record($entity, $new_config->get());
                } else {
                    $entity = $entity_storage->create_from_storage_record($new_config->get());
                }
                if ($entity->is_installable()) {
                    $entity->trust_data()->save();
                    if ($id !== $entity->id()) {
                        throw new \LogicException(sprintf('The configuration name "%s" does not match the ID "%s"', $name, $entity->id()));
                    }
                }
            } else {
                $new_config->save(true);
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function install_collection_default_config($collection): void
    {
        $storage = new Extension_Install_Storage($this->get_active_storages(Storage_Interface::DEFAULT_COLLECTION), Install_Storage::CONFIG_INSTALL_DIRECTORY, $collection, Installer_Kernel::installation_attempted(), $this->install_profile);
        // Only install configuration for enabled extensions.
        $enabled_extensions = $this->get_enabled_extensions();
        $config_to_install = array_filter($storage->list_all(), function ($config_name) use ($enabled_extensions): bool {
            $provider = mb_substr($config_name, 0, strpos($config_name, '.'));
            return in_array($provider, $enabled_extensions);
        });
        if (!empty($config_to_install)) {
            $this->create_configuration($collection, $storage->read_multiple($config_to_install));
            // Reset all the static caches and list caches.
            $this->config_factory->reset();
        }
    }
    /**
     * {@inheritdoc}
     */
    public function set_source_storage(Storage_Interface $storage): static
    {
        $this->source_storage = $storage;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_source_storage()
    {
        return $this->source_storage;
    }
    /**
     * Gets the configuration storage that provides the active configuration.
     *
     * @param string $collection
     *   (optional) The configuration collection. Defaults to the default
     *   collection.
     *
     * @return \Drupal\Core\Config\StorageInterface
     *   The configuration storage that provides the default configuration.
     */
    protected function get_active_storages($collection = Storage_Interface::DEFAULT_COLLECTION)
    {
        if (!isset($this->active_storages[$collection])) {
            $this->active_storages[$collection] = reset($this->active_storages)->create_collection($collection);
        }
        return $this->active_storages[$collection];
    }
    /**
     * {@inheritdoc}
     */
    public function set_syncing($status): static
    {
        if (!$status) {
            $this->source_storage = null;
        }
        $this->is_syncing = $status;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function is_syncing()
    {
        return $this->is_syncing;
    }
    /**
     * Finds pre-existing configuration objects for the provided extension.
     *
     * Extensions can not be installed if configuration objects exist in the
     * active storage with the same names. This can happen in a number of ways,
     * commonly:
     * - if a user has created configuration with the same name as that provided
     *   by the extension.
     * - if the extension provides default configuration that does not depend on
     *   it and the extension has been uninstalled and is about to the
     *   reinstalled.
     *
     * @param \Drupal\Core\Config\StorageInterface $storage
     *   The storage containing the default configuration.
     * @param array $previous_config_names
     *   An array of configuration names that have previously been checked.
     *
     * @return array
     *   Array of configuration object names that already exist keyed by
     *   collection.
     */
    protected function find_pre_existing_configuration(Storage_Interface $storage, array $previous_config_names = []): array
    {
        $existing_configuration = [];
        // Gather information about all the supported collections.
        $collection_info = $this->config_manager->get_config_collection_info();
        foreach ($collection_info->get_collection_names() as $collection) {
            $config_to_create = array_keys($this->get_config_to_create($storage, $collection));
            $active_storage = $this->get_active_storages($collection);
            foreach ($config_to_create as $config_name) {
                if ($active_storage->exists($config_name) || array_search($config_name, $previous_config_names[$collection] ?? [], true) !== false) {
                    $existing_configuration[$collection][] = $config_name;
                }
            }
        }
        return $existing_configuration;
    }
    /**
     * {@inheritdoc}
     */
    public function check_configuration_to_install($type, $name): void
    {
        if ($this->is_syncing()) {
            // Configuration is assumed to already be checked by the config importer
            // validation events.
            return;
        }
        $names = (array) $name;
        $enabled_extensions = $this->get_enabled_extensions();
        $previous_config_names = [];
        foreach ($names as $name) {
            // Add the extension that will be enabled to the list of enabled
            // extensions.
            $enabled_extensions[] = $name;
            $config_install_path = $this->get_default_config_directory($type, $name);
            if (!is_dir($config_install_path)) {
                continue;
            }
            $storage = new File_Storage($config_install_path, Storage_Interface::DEFAULT_COLLECTION);
            // Gets profile storages to search for overrides if necessary.
            $profile_storages = $this->get_profile_storages($name);
            // Check the dependencies of configuration provided by the module.
            [$invalid_default_config, $missing_dependencies] = $this->find_default_config_with_unmet_dependencies($storage, $enabled_extensions, $profile_storages, $previous_config_names);
            if (!empty($invalid_default_config)) {
                throw Unmet_Dependencies_Exception::create($name, array_unique($missing_dependencies, SORT_REGULAR));
            }
            // Install profiles can not have config clashes. Configuration that
            // has the same name as a module's configuration will be used instead.
            if ($name !== $this->drupal_get_profile()) {
                // Throw an exception if the module being installed contains
                // configuration that already exists. Additionally, can not continue
                // installing more modules because those may depend on the current
                // module being installed.
                $existing_configuration = $this->find_pre_existing_configuration($storage, $previous_config_names);
                if (!empty($existing_configuration)) {
                    throw Pre_Existing_Config_Exception::create($name, $existing_configuration);
                }
            }
            // Store the config names for the checked module in order to add them to
            // the list of active configuration for the next module.
            foreach ($this->config_manager->get_config_collection_info()->get_collection_names() as $collection) {
                $config_to_create = array_keys($this->get_config_to_create($storage, $collection));
                if (!isset($previous_config_names[$collection])) {
                    $previous_config_names[$collection] = $config_to_create;
                } else {
                    $previous_config_names[$collection] = array_merge($previous_config_names[$collection], $config_to_create);
                }
            }
        }
    }
    /**
     * Finds default configuration with unmet dependencies.
     *
     * @param \Drupal\Core\Config\StorageInterface $storage
     *   The storage containing the default configuration.
     * @param array $enabled_extensions
     *   A list of all the currently enabled modules and themes.
     * @param \Drupal\Core\Config\StorageInterface[] $profile_storages
     *   An array of storage interfaces containing profile configuration to check
     *   for overrides.
     * @param string[][] $previously_checked_config
     *   A list of previously checked configuration. Keyed by collection name.
     *
     * @return array
     *   An array containing:
     *     - A list of configuration that has unmet dependencies.
     *     - An array that will be filled with the missing dependency names, keyed
     *       by the dependents' names.
     */
    protected function find_default_config_with_unmet_dependencies(Storage_Interface $storage, array $enabled_extensions, array $profile_storages = [], array $previously_checked_config = []): array
    {
        $missing_dependencies = [];
        $config_to_create = $this->get_config_to_create($storage, Storage_Interface::DEFAULT_COLLECTION, '', $profile_storages);
        $all_config = array_merge($this->config_factory->list_all(), array_keys($config_to_create), $previously_checked_config[Storage_Interface::DEFAULT_COLLECTION] ?? []);
        foreach ($config_to_create as $config_name => $config) {
            if ($missing = $this->get_missing_dependencies($config_name, $config, $enabled_extensions, $all_config)) {
                $missing_dependencies[$config_name] = $missing;
            }
        }
        return [array_intersect_key($config_to_create, $missing_dependencies), $missing_dependencies];
    }
    /**
     * Validates an array of config data that contains dependency information.
     *
     * @param string $config_name
     *   The name of the configuration object that is being validated.
     * @param array $data
     *   Configuration data.
     * @param array $enabled_extensions
     *   A list of all the currently enabled modules and themes.
     * @param array $all_config
     *   A list of all the active configuration names.
     *
     * @return bool
     *   TRUE if all dependencies are present, FALSE otherwise.
     */
    protected function validate_dependencies($config_name, array $data, array $enabled_extensions, array $all_config): bool
    {
        if (!isset($data['dependencies'])) {
            // Simple config or a config entity without dependencies.
            [$provider] = explode('.', $config_name, 2);
            return in_array($provider, $enabled_extensions, true);
        }
        $missing = $this->get_missing_dependencies($config_name, $data, $enabled_extensions, $all_config);
        return empty($missing);
    }
    /**
     * Returns an array of missing dependencies for a config object.
     *
     * @param string $config_name
     *   The name of the configuration object that is being validated.
     * @param array $data
     *   Configuration data.
     * @param array $enabled_extensions
     *   A list of all the currently enabled modules and themes.
     * @param array $all_config
     *   A list of all the active configuration names.
     *
     * @return array
     *   A list of missing config dependencies.
     */
    protected function get_missing_dependencies($config_name, array $data, array $enabled_extensions, array $all_config): array
    {
        $missing = [];
        if (isset($data['dependencies'])) {
            [$provider] = explode('.', $config_name, 2);
            $all_dependencies = $data['dependencies'];
            // Ensure enforced dependencies are included.
            if (isset($all_dependencies['enforced'])) {
                $all_dependencies = Nested_Array::merge_deep($all_dependencies, $data['dependencies']['enforced']);
                unset($all_dependencies['enforced']);
            }
            // Ensure the configuration entity type provider is in the list of
            // dependencies.
            if (!isset($all_dependencies['module']) || !in_array($provider, $all_dependencies['module'])) {
                $all_dependencies['module'][] = $provider;
            }
            foreach ($all_dependencies as $type => $dependencies) {
                $list_to_check = [];
                switch ($type) {
                    case 'module':
                    case 'theme':
                        $list_to_check = $enabled_extensions;
                        break;
                    case 'config':
                        $list_to_check = $all_config;
                        break;
                }
                if (!empty($list_to_check)) {
                    $missing = array_merge($missing, array_diff($dependencies, $list_to_check));
                }
            }
        }
        return $missing;
    }
    /**
     * Gets the list of enabled extensions including both modules and themes.
     *
     * @return array
     *   A list of enabled extensions which includes both modules and themes.
     */
    protected function get_enabled_extensions(): array
    {
        // Read enabled extensions directly from configuration to avoid circular
        // dependencies on ModuleHandler and ThemeHandler.
        $extension_config = $this->config_factory->get('core.extension');
        $enabled_extensions = (array) $extension_config->get('module');
        $enabled_extensions += (array) $extension_config->get('theme');
        // Core can provide configuration.
        $enabled_extensions['core'] = 'core';
        return array_keys($enabled_extensions);
    }
    /**
     * Gets the profile storage to use to check for profile overrides.
     *
     * The install profile can override module configuration during a module
     * install. Both the install and optional directories are checked for matching
     * configuration. This allows profiles to override default configuration for
     * modules they do not depend on.
     *
     * @param string $installing_name
     *   (optional) The name of the extension currently being installed.
     *
     * @return \Drupal\Core\Config\StorageInterface[]|null
     *   Storages to access configuration from the installation profile. If we're
     *   installing the profile itself, then it will return an empty array as the
     *   profile storage should not be used.
     */
    protected function get_profile_storages($installing_name = ''): array
    {
        $profile = $this->drupal_get_profile();
        $profile_storages = [];
        if ($profile && $profile != $installing_name) {
            $profile_path = $this->extension_path_resolver->get_path('module', $profile);
            foreach ([Install_Storage::CONFIG_INSTALL_DIRECTORY, Install_Storage::CONFIG_OPTIONAL_DIRECTORY] as $directory) {
                if (is_dir($profile_path . '/' . $directory)) {
                    $profile_storages[] = new File_Storage($profile_path . '/' . $directory, Storage_Interface::DEFAULT_COLLECTION);
                }
            }
        }
        return $profile_storages;
    }
    /**
     * Gets an extension's default configuration directory.
     *
     * @param string $type
     *   Type of extension to install.
     * @param string $name
     *   Name of extension to install.
     *
     * @return string
     *   The extension's default configuration directory.
     */
    protected function get_default_config_directory(string $type, string $name): string
    {
        return $this->extension_path_resolver->get_path($type, $name) . '/' . Install_Storage::CONFIG_INSTALL_DIRECTORY;
    }
    /**
     * Gets the install profile from settings.
     *
     * @return string|null
     *   The name of the installation profile or NULL if no installation profile
     *   is currently active. This is the case for example during the first steps
     *   of the installer or during unit tests.
     */
    protected function drupal_get_profile()
    {
        return $this->install_profile;
    }
}