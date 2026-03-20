<?php

declare (strict_types=1);
namespace Drupal\Core\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Provides a console command to generate proxy classes.
 *
 * @see lazy_services
 * @see core/scripts/generate-proxy-class.php
 */
class Generate_Proxy_Class_Command extends Command
{
    /**
     * Constructs a new GenerateProxyClassCommand instance.
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
    protected function configure(): void
    {
        $this->set_name('generate-proxy-class')->set_definition([new Input_Argument('class_name', Input_Argument::REQUIRED, 'The class to be proxied'), new Input_Argument('namespace_root_path', Input_Argument::REQUIRED, 'The filepath to the root of the namespace.')])->set_description('Dumps a generated proxy class into its appropriate namespace.')->add_usage('\'Drupal\Core\Batch\BatchStorage\' "core/lib/Drupal/Core"')->add_usage('\'Drupal\block\BlockRepository\' "core/modules/block/src"')->add_usage('\'Drupal\my_module\MyClass\' "modules/contrib/my_module/src"');
    }
    /**
     * {@inheritdoc}
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $class_name = ltrim((string) $input->get_argument('class_name'), '\\');
        $namespace_root = $input->get_argument('namespace_root_path');
        $match = [];
        preg_match('/([a-zA-Z0-9_]+\\\\[a-zA-Z0-9_]+)\\\\(.+)/', $class_name, $match);
        if ($match) {
            $root_namespace = $match[1];
            $rest_fqcn = $match[2];
            $proxy_filename = $namespace_root . '/ProxyClass/' . str_replace('\\', '/', $rest_fqcn) . '.php';
            $proxy_class_name = $root_namespace . '\ProxyClass\\' . $rest_fqcn;
            $proxy_class_string = $this->proxy_builder->build($class_name);
            $file_string = <<<EOF
            <?php
            // phpcs:ignoreFile
            
            /**
             * This file was generated via php core/scripts/generate-proxy-class.php '{$class_name}' "{$namespace_root}".
             */
            {{ proxy_class_string }}
            EOF;
            $file_string = str_replace(['{{ proxy_class_name }}', '{{ proxy_class_string }}'], [$proxy_class_name, $proxy_class_string], $file_string);
            mkdir(dirname($proxy_filename), 0775, true);
            file_put_contents($proxy_filename, $file_string);
            $output->writeln(sprintf('Proxy of class %s written to %s', $class_name, $proxy_filename));
        }
        return 0;
    }
}