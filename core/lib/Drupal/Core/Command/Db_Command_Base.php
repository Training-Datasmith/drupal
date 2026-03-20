<?php

declare (strict_types=1);
namespace Drupal\Core\Command;

use Drupal\Core\Database\Database;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
/**
 * Base command that abstracts handling of database connection arguments.
 */
class Db_Command_Base extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->add_option('database', null, Input_Option::VALUE_OPTIONAL, 'The database connection name to use.', 'default')->add_option('database-url', 'db-url', Input_Option::VALUE_OPTIONAL, 'A database url to parse and use as the database connection.')->add_option('prefix', null, Input_Option::VALUE_OPTIONAL, 'Override or set the table prefix used in the database connection.');
    }
    /**
     * Parse input options decide on a database.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *   Input object.
     *
     * @return \Drupal\Core\Database\Connection
     *   The database connection.
     */
    protected function get_database_connection(Input_Interface $input)
    {
        // Load connection from a URL.
        if ($input->get_option('database-url')) {
            // @todo this could probably be refactored to not use a global connection.
            // Ensure database connection isn't set.
            if (Database::get_connection_info('db-tools')) {
                throw new \RuntimeException('Database "db-tools" is already defined. Cannot define database provided.');
            }
            $info = Database::convert_db_url_to_connection_info($input->get_option('database-url'));
            Database::add_connection_info('db-tools', 'default', $info);
            $key = 'db-tools';
        } else {
            $key = $input->get_option('database');
        }
        // If they supplied a prefix, replace it in the connection information.
        $prefix = $input->get_option('prefix');
        if ($prefix) {
            $info = Database::get_connection_info($key)['default'];
            $info['prefix'] = $prefix;
            Database::remove_connection($key);
            Database::add_connection_info($key, 'default', $info);
        }
        return Database::get_connection('default', $key);
    }
}