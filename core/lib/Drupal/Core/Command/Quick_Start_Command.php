<?php

declare (strict_types=1);
namespace Drupal\Core\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Array_Input;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Installs a Drupal site and starts a webserver for local testing/development.
 *
 * Wraps 'install' and 'server' commands.
 *
 * @internal
 *   This command makes no guarantee of an API for Drupal extensions.
 *
 * @see \Drupal\Core\Command\InstallCommand
 * @see \Drupal\Core\Command\ServerCommand
 */
class Quick_Start_Command extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->set_name('quick-start')->set_description('Installs a Drupal site and runs a web server. This is not meant for production and might be too simple for custom development. It is a quick and easy way to get Drupal running.')->add_argument('install-profile-or-recipe', Input_Argument::OPTIONAL, 'Install profile or recipe directory from which to install the site.')->add_option('langcode', null, Input_Option::VALUE_OPTIONAL, 'The language to install the site in. Defaults to en.', 'en')->add_option('password', null, Input_Option::VALUE_OPTIONAL, 'Set the administrator password. Defaults to a randomly generated password.')->add_option('site-name', null, Input_Option::VALUE_OPTIONAL, 'Set the site name. Defaults to Drupal.', 'Drupal')->add_option('host', null, Input_Option::VALUE_OPTIONAL, 'Provide a host for the server to run on. Defaults to 127.0.0.1.', '127.0.0.1')->add_option('port', null, Input_Option::VALUE_OPTIONAL, 'Provide a port for the server to run on. Will be determined automatically if none supplied.')->add_option('suppress-login', 's', Input_Option::VALUE_NONE, 'Disable opening a login URL in a browser.')->add_usage('demo_umami --langcode fr')->add_usage('standard --site-name QuickInstall --host localhost --port 8080')->add_usage('minimal --host my-site.com --port 80')->add_usage('core/recipes/standard --site-name MyDrupalRecipe');
        parent::configure();
    }
    /**
     * {@inheritdoc}
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $command = $this->get_application()->find('install');
        $arguments = ['command' => 'install', 'install-profile-or-recipe' => $input->get_argument('install-profile-or-recipe'), '--langcode' => $input->get_option('langcode'), '--password' => $input->get_option('password'), '--site-name' => $input->get_option('site-name')];
        $install_input = new Array_Input($arguments);
        $return_code = $command->run($install_input, $output);
        if ($return_code === 0) {
            $command = $this->get_application()->find('server');
            $arguments = ['command' => 'server', '--host' => $input->get_option('host'), '--port' => $input->get_option('port')];
            if ($input->get_option('suppress-login')) {
                $arguments['--suppress-login'] = true;
            }
            $server_input = new Array_Input($arguments);
            $return_code = $command->run($server_input, $output);
        }
        return $return_code;
    }
}