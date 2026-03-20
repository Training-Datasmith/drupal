<?php

declare (strict_types=1);
namespace Drupal\Core\Command;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Schema_Object_Exists_Exception;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Provides a command to import the current database from a script.
 *
 * This script runs on databases exported using one of the database dump
 * commands and imports it into the current database connection.
 *
 * @see \Drupal\Core\Command\DbImportApplication
 */
class Db_Import_Command extends Db_Command_Base
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this->set_name('import')->set_description('Import database from a generation script.')->add_argument('script', Input_Option::VALUE_REQUIRED, 'Import script');
    }
    /**
     * {@inheritdoc}
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $script = $input->get_argument('script');
        if (!is_file($script)) {
            $output->writeln('File must exist.');
            return 1;
        }
        $connection = $this->get_database_connection($input);
        $this->run_script($connection, $script);
        $output->writeln('Import completed successfully.');
        return 0;
    }
    /**
     * Run the database script.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   Connection used by the script when included.
     * @param string $script
     *   Path to dump script.
     */
    protected function run_script(Connection $connection, $script)
    {
        $old_key = Database::set_active_connection($connection->get_key());
        if (str_ends_with($script, '.gz')) {
            $script = "compress.zlib://{$script}";
        }
        try {
            require $script;
        } catch (Schema_Object_Exists_Exception) {
            throw new \RuntimeException('An existing Drupal installation exists at this location. Try removing all tables or changing the database prefix in your settings.php file.');
        }
        Database::set_active_connection($old_key);
    }
}