<?php

declare (strict_types=1);
namespace Drupal\Core\Command;

use Symfony\Component\Console\Application;
/**
 * Provides a command to import a database generation script.
 */
class Db_Tools_Application extends Application
{
    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct('Database Tools', \Drupal::VERSION);
    }
    /**
     * {@inheritdoc}
     */
    protected function get_default_commands(): array
    {
        $default_commands = parent::get_default_commands();
        $default_commands[] = new Db_Dump_Command();
        $default_commands[] = new Db_Import_Command();
        return $default_commands;
    }
}