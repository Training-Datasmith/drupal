<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

use Composer\Autoload\Class_Loader;
use Drupal\Core\Cache\Null_Backend;
use Drupal\Core\Database\Event\Statement_Event;
use Drupal\Core\Extension\Database_Driver_List;
/**
 * Primary front-controller for the database system.
 *
 * This class is un-extendable. It acts to encapsulate all control and
 * shepherding of database connections into a single location without the use of
 * globals.
 *
 * @final
 */
abstract class Database
{
    /**
     * A nested array of active connections, keyed by database name and target.
     *
     * @var array
     */
    protected static $connections = [];
    /**
     * A processed copy of the database connection information from settings.php.
     *
     * @var array
     */
    protected static $database_info = [];
    /**
     * A list of key/target credentials to simply ignore.
     *
     * @var array
     */
    protected static $ignore_targets = [];
    /**
     * The key of the currently active database connection.
     *
     * @var string
     */
    protected static $active_key = 'default';
    /**
     * An array of active query log objects.
     *
     * @var array
     * Every connection has one and only one logger object for all targets and
     * logging keys.
     *
     * @code
     *   [
     *     '$db_key' => DatabaseLog object.
     *   ]
     * @endcode
     */
    protected static $logs = [];
    /**
     * Starts logging a given logging key on the specified connection.
     *
     * @param string $logging_key
     *   The logging key to log.
     * @param string $key
     *   The database connection key for which we want to log.
     *
     * @return \Drupal\Core\Database\Log
     *   The query log object. Note that the log object does support richer
     *   methods than the few exposed through the Database class, so in some
     *   cases it may be desirable to access it directly.
     *
     * @see \Drupal\Core\Database\Log
     */
    final public static function start_log($logging_key, $key = 'default')
    {
        if (empty(self::$logs[$key])) {
            self::$logs[$key] = new Log($key);
            // Every target already active for this connection key needs to have the
            // logging object associated with it.
            if (!empty(self::$connections[$key])) {
                foreach (self::$connections[$key] as $connection) {
                    $connection->enable_events(Statement_Event::all());
                    $connection->set_logger(self::$logs[$key]);
                }
            }
        }
        self::$logs[$key]->start($logging_key);
        return self::$logs[$key];
    }
    /**
     * Retrieves the queries logged on for given logging key.
     *
     * This method also ends logging for the specified key. To get the query log
     * to date without ending the logger request the logging object by starting
     * it again (which does nothing to an open log key) and call methods on it as
     * desired.
     *
     * @param string $logging_key
     *   The logging key to log.
     * @param string $key
     *   The database connection key for which we want to log.
     *
     * @return array
     *   The query log for the specified logging key and connection.
     *
     * @see \Drupal\Core\Database\Log
     */
    final public static function get_log($logging_key, $key = 'default')
    {
        if (empty(self::$logs[$key])) {
            return [];
        }
        $queries = self::$logs[$key]->get($logging_key);
        self::$logs[$key]->end($logging_key);
        return $queries;
    }
    /**
     * Gets the connection object for the specified database key and target.
     *
     * @param string $target
     *   The database target name.
     * @param string $key
     *   The database connection key. Defaults to NULL which means the active key.
     *
     * @return \Drupal\Core\Database\Connection
     *   The corresponding connection object.
     */
    final public static function get_connection($target = 'default', $key = null)
    {
        if (!isset($key)) {
            // By default, we want the active connection, set in setActiveConnection.
            $key = self::$active_key;
        }
        // If the requested target does not exist, or if it is ignored, we fall back
        // to the default target. The target is typically either "default" or
        // "replica", indicating to use a replica SQL server if one is available. If
        // it's not available, then the default/primary server is the correct server
        // to use.
        if (!empty(self::$ignore_targets[$key][$target]) || !isset(self::$database_info[$key][$target])) {
            $target = 'default';
        }
        if (!isset(self::$connections[$key][$target])) {
            // If necessary, a new connection is opened.
            self::$connections[$key][$target] = self::open_connection($key, $target);
        }
        return self::$connections[$key][$target];
    }
    /**
     * Determines if there is an active connection.
     *
     * Note that this method will return FALSE if no connection has been
     * established yet, even if one could be.
     *
     * @return bool
     *   TRUE if there is at least one database connection established, FALSE
     *   otherwise.
     */
    final public static function is_active_connection()
    {
        return !empty(self::$active_key) && !empty(self::$connections) && !empty(self::$connections[self::$active_key]);
    }
    /**
     * Sets the active connection to the specified key.
     *
     * @return string|null
     *   The previous database connection key.
     */
    final public static function set_active_connection($key = 'default')
    {
        if (!empty(self::$database_info[$key])) {
            $old_key = self::$active_key;
            self::$active_key = $key;
            return $old_key;
        }
    }
    /**
     * Process the configuration file for database information.
     *
     * @param array $info
     *   The database connection information, as defined in settings.php. The
     *   structure of this array depends on the database driver it is connecting
     *   to.
     */
    final public static function parse_connection_info(array $info)
    {
        // If there is no "driver" property, then we assume it's an array of
        // possible connections for this target. Pick one at random. That allows
        // us to have, for example, multiple replica servers.
        if (empty($info['driver'])) {
            $info = $info[mt_rand(0, count($info) - 1)];
        }
        // Prefix information, default to an empty prefix.
        $info['prefix'] ??= '';
        // Backwards compatibility layer for Drupal 8 style database connection
        // arrays. Those have the wrong 'namespace' key set, or not set at all
        // for core supported database drivers.
        if (empty($info['namespace']) || str_starts_with((string) $info['namespace'], 'Drupal\Core\Database\Driver\\')) {
            switch (strtolower((string) $info['driver'])) {
                case 'mysql':
                    $info['namespace'] = 'Drupal\mysql\Driver\Database\mysql';
                    break;
                case 'pgsql':
                    $info['namespace'] = 'Drupal\pgsql\Driver\Database\pgsql';
                    break;
                case 'sqlite':
                    $info['namespace'] = 'Drupal\sqlite\Driver\Database\sqlite';
                    break;
            }
        }
        // Backwards compatibility layer for Drupal 8 style database connection
        // arrays. Those do not have the 'autoload' key set for core database
        // drivers.
        if (empty($info['autoload'])) {
            switch (trim((string) $info['namespace'], '\\')) {
                case 'Drupal\mysql\Driver\Database\mysql':
                    $info['autoload'] = 'core/modules/mysql/src/Driver/Database/mysql/';
                    break;
                case 'Drupal\pgsql\Driver\Database\pgsql':
                    $info['autoload'] = 'core/modules/pgsql/src/Driver/Database/pgsql/';
                    break;
                case 'Drupal\sqlite\Driver\Database\sqlite':
                    $info['autoload'] = 'core/modules/sqlite/src/Driver/Database/sqlite/';
                    break;
            }
        }
        return $info;
    }
    /**
     * Adds database connection information for a given key/target.
     *
     * This method allows to add new connections at runtime.
     *
     * Under normal circumstances the preferred way to specify database
     * credentials is via settings.php. However, this method allows them to be
     * added at arbitrary times, such as during unit tests, when connecting to
     * admin-defined third party databases, etc. Use
     * \Drupal\Core\Database\Database::setActiveConnection to select the
     * connection to use.
     *
     * If the given key/target pair already exists, this method will be ignored.
     *
     * @param string $key
     *   The database key.
     * @param string $target
     *   The database target name.
     * @param array $info
     *   The database connection information, as defined in settings.php. The
     *   structure of this array depends on the database driver it is connecting
     *   to.
     * @param \Composer\Autoload\ClassLoader $class_loader
     *   The class loader. Used for adding the database driver to the autoloader
     *   if $info['autoload'] is set.
     * @param string $app_root
     *   The app root.
     *
     * @see \Drupal\Core\Database\Database::setActiveConnection
     */
    final public static function add_connection_info($key, $target, array $info, $class_loader = null, $app_root = null): void
    {
        if (empty(self::$database_info[$key][$target])) {
            $info = self::parse_connection_info($info);
            self::$database_info[$key][$target] = $info;
            // If the database driver is provided by a module, then its code may need
            // to be instantiated prior to when the module's root namespace is added
            // to the autoloader, because that happens during service container
            // initialization but the container definition is likely in the database.
            // Therefore, allow the connection info to specify an autoload directory
            // for the driver.
            if (isset($info['autoload']) && $class_loader && $app_root) {
                $class_loader->add_psr4($info['namespace'] . '\\', $app_root . '/' . $info['autoload']);
                // When the database driver is extending from other database drivers,
                // then add autoload directory for the parent database driver modules
                // as well.
                if (!empty($info['dependencies'])) {
                    assert(is_array($info['dependencies']));
                    foreach ($info['dependencies'] as $dependency) {
                        if (isset($dependency['namespace']) && isset($dependency['autoload'])) {
                            $class_loader->add_psr4($dependency['namespace'] . '\\', $app_root . '/' . $dependency['autoload']);
                        }
                    }
                }
            }
        }
    }
    /**
     * Gets information on the specified database connection.
     *
     * @param string $key
     *   (optional) The connection key for which to return information.
     *
     * @return array|null
     *   An associative array of database information. Defaults to an empty array.
     */
    final public static function get_connection_info($key = 'default')
    {
        if (!empty(self::$database_info[$key])) {
            return self::$database_info[$key];
        }
    }
    /**
     * Gets connection information for all available databases.
     *
     * @return array
     *   An associative array of database information for all available database,
     *   keyed by the database name. Defaults to an empty array.
     */
    final public static function get_all_connection_info()
    {
        return self::$database_info;
    }
    /**
     * Sets connection information for multiple databases.
     *
     * @param array $databases
     *   A multi-dimensional array specifying database connection parameters, as
     *   defined in settings.php.
     * @param \Composer\Autoload\ClassLoader $class_loader
     *   The class loader. Used for adding the database driver(s) to the
     *   autoloader if $databases[$key][$target]['autoload'] is set.
     * @param string $app_root
     *   The app root.
     */
    final public static function set_multiple_connection_info(array $databases, $class_loader = null, $app_root = null): void
    {
        foreach ($databases as $key => $targets) {
            foreach ($targets as $target => $info) {
                self::add_connection_info($key, $target, $info, $class_loader, $app_root);
            }
        }
    }
    /**
     * Rename a connection and its corresponding connection information.
     *
     * @param string $old_key
     *   The old connection key.
     * @param string $new_key
     *   The new connection key.
     *
     * @return bool
     *   TRUE in case of success, FALSE otherwise.
     */
    final public static function rename_connection($old_key, $new_key)
    {
        if (!empty(self::$database_info[$old_key]) && empty(self::$database_info[$new_key])) {
            // Migrate the database connection information.
            self::$database_info[$new_key] = self::$database_info[$old_key];
            unset(self::$database_info[$old_key]);
            // Migrate over the DatabaseConnection object if it exists.
            if (isset(self::$connections[$old_key])) {
                self::$connections[$new_key] = self::$connections[$old_key];
                unset(self::$connections[$old_key]);
            }
            return true;
        }
        return false;
    }
    /**
     * Remove a connection and its corresponding connection information.
     *
     * @param string $key
     *   The connection key.
     *
     * @return bool
     *   TRUE in case of success, FALSE otherwise.
     */
    final public static function remove_connection($key)
    {
        if (isset(self::$database_info[$key])) {
            self::close_connection(null, $key);
            unset(self::$database_info[$key]);
            return true;
        }
        return false;
    }
    /**
     * Opens a connection to the server specified by the given key and target.
     *
     * @param string $key
     *   The database connection key, as specified in settings.php. The default is
     *   "default".
     * @param string $target
     *   The database target to open.
     *
     * @throws \Drupal\Core\Database\ConnectionNotDefinedException
     * @throws \Drupal\Core\Database\DriverNotSpecifiedException
     */
    final protected static function open_connection($key, $target)
    {
        // If the requested database does not exist then it is an unrecoverable
        // error.
        if (!isset(self::$database_info[$key])) {
            throw new Connection_Not_Defined_Exception('The specified database connection is not defined: ' . $key);
        }
        if (!self::$database_info[$key][$target]['driver']) {
            throw new Driver_Not_Specified_Exception('Driver not specified for this database connection: ' . $key);
        }
        $driver_class = self::$database_info[$key][$target]['namespace'] . '\Connection';
        $client_connection = $driver_class::open(self::$database_info[$key][$target]);
        $new_connection = new $driver_class($client_connection, self::$database_info[$key][$target]);
        $new_connection->set_target($target);
        $new_connection->set_key($key);
        // If we have any active logging objects for this connection key, we need
        // to associate them with the connection we just opened.
        if (!empty(self::$logs[$key])) {
            $new_connection->enable_events(Statement_Event::all());
            $new_connection->set_logger(self::$logs[$key]);
        }
        return $new_connection;
    }
    /**
     * Closes a connection to the server specified by the given key and target.
     *
     * @param string $target
     *   The database target name.  Defaults to NULL meaning that all target
     *   connections will be closed.
     * @param string $key
     *   The database connection key. Defaults to NULL which means the active key.
     */
    public static function close_connection($target = null, $key = null): void
    {
        // Gets the active connection by default.
        if (!isset($key)) {
            $key = self::$active_key;
        }
        if (isset($target) && isset(self::$connections[$key][$target])) {
            if (self::$connections[$key][$target] instanceof Connection) {
                self::$connections[$key][$target]->commit_all();
            }
            unset(self::$connections[$key][$target]);
        } elseif (isset(self::$connections[$key])) {
            foreach (self::$connections[$key] as $connection) {
                if ($connection instanceof Connection) {
                    $connection->commit_all();
                }
            }
            unset(self::$connections[$key]);
        }
        // When last connection for $key is closed, we also stop any active
        // logging.
        if (empty(self::$connections[$key])) {
            unset(self::$logs[$key]);
        }
        // Force garbage collection to run. This ensures that client connection
        // objects and results in the connection being closed are destroyed.
        gc_collect_cycles();
    }
    /**
     * Instructs the system to temporarily ignore a given key/target.
     *
     * At times we need to temporarily disable replica queries. To do so, call
     * this method with the database key and the target to disable. That database
     * key will then always fall back to 'default' for that key, even if it's
     * defined.
     *
     * @param string $key
     *   The database connection key.
     * @param string $target
     *   The target of the specified key to ignore.
     */
    public static function ignore_target($key, $target): void
    {
        self::$ignore_targets[$key][$target] = true;
    }
    /**
     * Converts a URL to a database connection info array.
     *
     * @param string $url
     *   The URL.
     * @param bool|null $include_test_drivers
     *   (optional) Whether to include test extensions. If FALSE, all 'tests'
     *   directories are excluded in the search. When NULL will be determined by
     *   the extension_discovery_scan_tests setting.
     *
     * @return array
     *   The database connection info.
     *
     * @throws \InvalidArgumentException
     *   Exception thrown when the provided URL does not meet the minimum
     *   requirements.
     * @throws \RuntimeException
     *   Exception thrown when a module provided database driver does not exist.
     */
    public static function convert_db_url_to_connection_info(string $url, ?bool $include_test_drivers = null): array
    {
        // Check that the URL is well formed, starting with 'scheme://', where
        // 'scheme' is a database driver name.
        if (preg_match('/^(.*):\/\//', $url, $matches) !== 1) {
            throw new \InvalidArgumentException("Missing scheme in URL '{$url}'");
        }
        $driver_name = $matches[1];
        // Determine if the database driver is provided by a module.
        // @todo https://www.drupal.org/project/drupal/issues/3250999. Refactor when
        // all database drivers are provided by modules.
        $url_components = parse_url($url);
        $url_component_query = $url_components['query'] ?? '';
        parse_str($url_component_query, $query);
        // Use the driver name as the module name when the module name is not
        // provided.
        $module = $query['module'] ?? $driver_name;
        $driver_namespace = "Drupal\\{$module}\\Driver\\Database\\{$driver_name}";
        /** @var \Drupal\Core\Extension\DatabaseDriver $driver */
        $driver = self::get_driver_list()->include_test_drivers($include_test_drivers)->get($driver_namespace);
        // Set up an additional autoloader. We don't use the main autoloader as
        // this method can be called before Drupal is installed and is never
        // called during regular runtime.
        $additional_class_loader = new Class_Loader();
        $additional_class_loader->add_psr4($driver_namespace . '\\', $driver->get_path());
        $additional_class_loader->register();
        $connection_class = $driver_namespace . '\Connection';
        if (!class_exists($connection_class)) {
            throw new \InvalidArgumentException("Can not convert '{$url}' to a database connection, class '{$connection_class}' does not exist");
        }
        // When the database driver is extending another database driver, then
        // add autoload info for the parent database driver as well.
        $autoload_info = $driver->get_autoload_info();
        if (isset($autoload_info['dependencies'])) {
            foreach ($autoload_info['dependencies'] as $dependency) {
                $additional_class_loader->add_psr4($dependency['namespace'] . '\\', $dependency['autoload']);
            }
        }
        $additional_class_loader->register(true);
        $options = $connection_class::create_connection_options_from_url($url);
        // Add the necessary information to autoload code.
        // @see \Drupal\Core\Site\Settings::initialize()
        $options['autoload'] = $driver->get_path() . DIRECTORY_SEPARATOR;
        if (isset($autoload_info['dependencies'])) {
            $options['dependencies'] = $autoload_info['dependencies'];
        }
        return $options;
    }
    /**
     * Returns the list provider for available database drivers.
     *
     * @return \Drupal\Core\Extension\DatabaseDriverList
     *   The list provider for available database drivers.
     */
    public static function get_driver_list(): Database_Driver_List
    {
        if (\Drupal::has_container() && \Drupal::has_service('extension.list.database_driver')) {
            return \Drupal::service('extension.list.database_driver');
        }
        return new Database_Driver_List(DRUPAL_ROOT, 'database_driver', new Null_Backend('database_driver'));
    }
    /**
     * Gets database connection info as a URL.
     *
     * @param string $key
     *   (Optional) The database connection key.
     *
     * @return string
     *   The connection info as a URL.
     *
     * @throws \RuntimeException
     *   When the database connection is not defined.
     */
    public static function get_connection_info_as_url($key = 'default')
    {
        $db_info = static::get_connection_info($key);
        if (empty($db_info) || empty($db_info['default'])) {
            throw new \RuntimeException("Database connection {$key} not defined or missing the 'default' settings");
        }
        $namespace = $db_info['default']['namespace'];
        // Add the module name to the connection options to make it easy for the
        // connection class's createUrlFromConnectionOptions() method to add it to
        // the URL.
        $db_info['default']['module'] = explode('\\', (string) $namespace)[1];
        $connection_class = $namespace . '\Connection';
        return $connection_class::create_url_from_connection_options($db_info['default']);
    }
    /**
     * Calls commitAll() on all the open connections.
     *
     * If drupal_register_shutdown_function() exists the commit will occur during
     * shutdown so that it occurs at the latest possible moment.
     *
     * @param bool $shutdown
     *   Internal param to denote that the method is being called by
     *   _drupal_shutdown_function().
     *
     * @internal
     *   This method exists only to work around a bug caused by Drupal incorrectly
     *   relying on object destruction order to commit transactions. Xdebug 3.3.0
     *   changes the order of object destruction when the develop mode is enabled.
     */
    public static function commit_all_on_shutdown(bool $shutdown = false): void
    {
        static $registered = false;
        if ($shutdown) {
            foreach (self::$connections as $targets) {
                foreach ($targets as $connection) {
                    if ($connection instanceof Connection) {
                        $connection->commit_all();
                    }
                }
            }
            return;
        }
        if (!function_exists('drupal_register_shutdown_function')) {
            return;
        }
        if (!$registered) {
            $registered = true;
            drupal_register_shutdown_function('\Drupal\Core\Database\Database::commitAllOnShutdown', true);
        }
    }
}