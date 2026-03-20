<?php

declare (strict_types=1);
namespace Drupal\Core\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Console\Input\Input_Interface;
/**
 * Provides a command to dump a database generation script.
 */
class Db_Dump_Application extends Application
{
    /**
     * {@inheritdoc}
     */
    protected function get_command_name(Input_Interface $input): ?string
    {
        return 'dump-database-d8-mysql';
    }
    /**
     * {@inheritdoc}
     */
    protected function get_default_commands(): array
    {
        // Even though this is a single command, keep the HelpCommand (--help).
        $default_commands = parent::get_default_commands();
        $default_commands[] = new Db_Dump_Command();
        return $default_commands;
    }
    /**
     * {@inheritdoc}
     *
     * Overridden so the application doesn't expect the command name as the first
     * argument.
     */
    public function get_definition(): Input_Definition
    {
        $definition = parent::get_definition();
        // Clears the normal first argument (the command name).
        $definition->set_arguments();
        return $definition;
    }
}