<?php

declare (strict_types=1);
namespace Drupal\Core\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Console\Input\Input_Interface;
/**
 * Provides a console command to generate proxy classes.
 *
 * @see lazy_services
 * @see core/scripts/generate-proxy-class.php
 */
class Generate_Proxy_Class_Application extends Application
{
    /**
     * Constructs a new GenerateProxyClassApplication instance.
     *
     * @param \Drupal\Component\ProxyBuilder\ProxyBuilder $proxyBuilder
     *   The proxy builder.
     */
    public function __construct(protected \Drupal\Component\Proxy_Builder\Proxy_Builder $proxy_builder)
    {
        parent::__construct();
    }
    /**
     * {@inheritdoc}
     */
    protected function get_command_name(Input_Interface $input): ?string
    {
        return 'generate-proxy-class';
    }
    /**
     * {@inheritdoc}
     */
    protected function get_default_commands(): array
    {
        // Even though this is a single command, keep the HelpCommand (--help).
        $default_commands = parent::get_default_commands();
        $default_commands[] = new Generate_Proxy_Class_Command($this->proxy_builder);
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