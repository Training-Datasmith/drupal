<?php

declare (strict_types=1);
namespace Drupal\Core\Command;

use Composer\Autoload\Class_Loader;
use Composer\Semver\Version_Parser;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\Extension_Discovery;
use Drupal\Core\Extension\Info_Parser;
use Drupal\Core\Theme\Starter_Kit_Interface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Question\Confirmation_Question;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;
use Symfony\Component\Process\Process;
use function Symfony\Component\String\u;
/**
 * Generates a new theme based on latest default markup.
 */
class Generate_Theme extends Command
{
    /**
     * The path for the Drupal root.
     */
    private readonly string $root;
    /**
     * GenerateTheme constructor.
     *
     * @param string|null $name
     *   The name of the command; passing null means it must be set in
     *   configure().
     * @param string|null $root
     *   The path for the Drupal root.
     */
    public function __construct(?string $name = null, ?string $root = null)
    {
        parent::__construct($name);
        $this->root = $root ?? dirname(__DIR__, 5);
    }
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->set_name('generate-theme')->set_description('Generates a new theme based on latest default markup.')->add_argument('machine-name', Input_Argument::REQUIRED, 'The machine name of the generated theme')->add_option('name', null, Input_Option::VALUE_OPTIONAL, 'A name for the theme.')->add_option('description', null, Input_Option::VALUE_OPTIONAL, 'A description of your theme.', '')->add_option('path', null, Input_Option::VALUE_OPTIONAL, 'The path where your theme will be created. Defaults to: themes', 'themes')->add_option('starterkit', null, Input_Option::VALUE_OPTIONAL, 'The theme to use as the starterkit', 'starterkit_theme')->add_usage('custom_theme --name "Custom Theme" --description "Custom theme generated from a starterkit theme" --path themes')->add_usage('custom_theme --name "Custom Theme" --starterkit mystarterkit');
    }
    /**
     * {@inheritdoc}
     */
    protected function initialize(Input_Interface $input, Output_Interface $output): void
    {
        if ($input->get_option('name') === null) {
            $input->set_option('name', $input->get_argument('machine-name'));
        }
        // Change the directory to the Drupal root.
        chdir($this->root);
    }
    /**
     * {@inheritdoc}
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $filesystem = new Filesystem();
        $tmp_dir = $this->get_unique_tmp_dir_path();
        $destination_theme = $input->get_argument('machine-name');
        $starterkit_id = $input->get_option('starterkit');
        $theme_label = $input->get_option('name');
        $io->writeln("<info>Generating theme {$theme_label} ({$destination_theme}) from {$starterkit_id} starterkit.</info>");
        $destination = trim((string) $input->get_option('path'), '/') . '/' . $destination_theme;
        if (is_dir($destination)) {
            $io->get_error_style()->error("Theme could not be generated because the destination directory {$destination} exists already.");
            return 1;
        }
        $starterkit = $this->get_theme_info($starterkit_id);
        if ($starterkit === null) {
            $io->get_error_style()->error("Theme source theme {$starterkit_id} cannot be found.");
            return 1;
        }
        $io->writeln("Trying to parse version for {$starterkit_id} starterkit.", Output_Interface::VERBOSITY_DEBUG);
        try {
            $starterkit_version = self::get_starter_kit_version($starterkit, $io);
        } catch (\Exception $e) {
            $io->get_error_style()->error($e->get_message());
            return 1;
        }
        $io->writeln("Using version {$starterkit_version} for {$starterkit_id} starterkit.", Output_Interface::VERBOSITY_DEBUG);
        $io->writeln("Loading starterkit config from {$starterkit_id}.starterkit.yml.", Output_Interface::VERBOSITY_DEBUG);
        try {
            $starterkit_config = self::load_starter_kit_config($starterkit, $starterkit_version, $theme_label, $input->get_option('description'));
        } catch (\Exception $e) {
            $io->get_error_style()->error($e->get_message());
            return 1;
        }
        $filesystem->mkdir($tmp_dir);
        $io->writeln('Copying starterkit to temporary directory for processing.', Output_Interface::VERBOSITY_DEBUG);
        $mirror_iterator = (new Finder())->in($starterkit->get_path())->files()->ignore_dot_files(false)->not_name($starterkit_config['ignore'])->not_path($starterkit_config['ignore']);
        $filesystem->mirror($starterkit->get_path(), $tmp_dir, $mirror_iterator);
        $io->writeln('Modifying and renaming files from starterkit.', Output_Interface::VERBOSITY_DEBUG);
        $patterns = ['old' => self::name_patterns($starterkit->get_name(), $starterkit->info['name']), 'new' => self::name_patterns($destination_theme, $theme_label)];
        $files_to_edit = self::create_files_finder($tmp_dir)->contains(array_values($patterns['old']))->not_path($starterkit_config['no_edit']);
        foreach ($files_to_edit as $file) {
            $contents = file_get_contents($file->get_real_path());
            $contents = str_replace($patterns['old'], $patterns['new'], $contents);
            file_put_contents($file->get_real_path(), $contents);
        }
        $files_to_rename = self::create_files_finder($tmp_dir)->name(array_map(static fn(string $pattern): string => "*{$pattern}*", array_values($patterns['old'])))->not_path($starterkit_config['no_rename']);
        foreach ($files_to_rename as $file) {
            $filepath_segments = explode('/', $file->get_real_path());
            $filename = array_pop($filepath_segments);
            $filename = str_replace($patterns['old'], $patterns['new'], $filename);
            $filepath_segments[] = $filename;
            $filesystem->rename($file->get_real_path(), implode('/', $filepath_segments));
        }
        $io->writeln("Updating {$destination_theme}.info.yml.", Output_Interface::VERBOSITY_DEBUG);
        $info_file = "{$tmp_dir}/{$destination_theme}.info.yml";
        $info = Yaml::decode(file_get_contents($info_file));
        $info = array_filter(array_merge($info, $starterkit_config['info']), static fn(mixed $value): bool => $value !== null);
        // Ensure the generated theme is not hidden.
        unset($info['hidden']);
        file_put_contents($info_file, Yaml::encode($info));
        $loader = new Class_Loader();
        $loader->add_psr4("Drupal\\{$starterkit->get_name()}\\", "{$starterkit->get_path()}/src");
        $loader->register();
        $generator_classname = "Drupal\\{$starterkit->get_name()}\\StarterKit";
        if (class_exists($generator_classname)) {
            if (is_a($generator_classname, Starter_Kit_Interface::class, true)) {
                $io->writeln('Running post processing.', Output_Interface::VERBOSITY_DEBUG);
                $generator_classname::post_process($tmp_dir, $destination_theme, $theme_label);
            } else {
                $io->get_error_style()->error("The {$generator_classname} does not implement \\Drupal\\Core\\Theme\\StarterKitInterface and cannot perform post-processing.");
                return 1;
            }
        } else {
            $io->writeln("Skipping post processing, {$generator_classname} not defined.", Output_Interface::VERBOSITY_DEBUG);
        }
        // Move altered theme to final destination.
        $io->writeln("Copying {$destination_theme} to {$destination}.", Output_Interface::VERBOSITY_DEBUG);
        $filesystem->mirror($tmp_dir, $destination);
        $io->writeln(sprintf('Theme generated successfully to %s', $destination));
        return 0;
    }
    /**
     * Generates a path to a temporary location.
     *
     * @return string
     *   A temporary path.
     */
    private function get_unique_tmp_dir_path(): string
    {
        return sys_get_temp_dir() . '/drupal-starterkit-theme-' . uniqid(md5(microtime()), true);
    }
    /**
     * Gets theme info using the theme name.
     *
     * @param string $theme_name
     *   The machine name of the theme.
     *
     * @return \Drupal\Core\Extension\Extension|null
     *   The extension info array. NULL if the theme_name is not discovered.
     */
    private function get_theme_info(string $theme_name): ?Extension
    {
        $extension_discovery = new Extension_Discovery($this->root, false, []);
        $themes = $extension_discovery->scan('theme');
        $theme = $themes[$theme_name] ?? null;
        if ($theme !== null) {
            $theme->info = (new Info_Parser($this->root))->parse($theme->get_pathname());
        }
        return $theme;
    }
    /**
     * Returns a Symfony file finder.
     */
    private static function create_files_finder(string $dir): Finder
    {
        return (new Finder())->in($dir)->files();
    }
    /**
     * Loads the Starter Kit configuration.
     */
    private static function load_starter_kit_config(Extension $theme, string $version, string $name, string $description): array
    {
        $starterkit_config_file = $theme->get_path() . '/' . $theme->get_name() . '.starterkit.yml';
        if (!file_exists($starterkit_config_file)) {
            throw new \RuntimeException("Theme source theme {$theme->get_name()} is not a valid starter kit.");
        }
        $starterkit_config_defaults = ['info' => ['name' => $name, 'description' => $description, 'core_version_requirement' => '^' . explode('.', \Drupal::VERSION)[0], 'version' => '1.0.0', 'generator' => "{$theme->get_name()}:{$version}"], 'ignore' => ['/src/StarterKit.php', '/*.starterkit.yml'], 'no_edit' => [], 'no_rename' => []];
        $starterkit_config = Yaml::decode(file_get_contents($starterkit_config_file));
        if (!is_array($starterkit_config)) {
            throw new \RuntimeException('Starterkit config is was not able to be parsed.');
        }
        if (!isset($starterkit_config['info'])) {
            $starterkit_config['info'] = [];
        }
        $starterkit_config['info'] = array_merge($starterkit_config_defaults['info'], $starterkit_config['info']);
        foreach (['ignore', 'no_edit', 'no_rename'] as $key) {
            if (!isset($starterkit_config[$key])) {
                $starterkit_config[$key] = $starterkit_config_defaults[$key];
            }
            if (!is_array($starterkit_config[$key])) {
                throw new \RuntimeException("{$key} in starterkit.yml must be an array");
            }
            $starterkit_config[$key] = array_map(static fn(string $path): string => Glob::to_regex(trim($path, '/')), $starterkit_config[$key]);
            if (count($starterkit_config[$key]) > 0) {
                $files = self::create_files_finder($theme->get_path())->path($starterkit_config[$key])->ignore_dot_files(false)->ignore_vcs(true);
                $starterkit_config[$key] = array_map(static fn(\Symfony\Component\Finder\Spl_File_Info $file): string => $file->get_relative_pathname(), iterator_to_array($files));
                if (count($starterkit_config[$key]) === 0) {
                    throw new \RuntimeException("Paths were defined `{$key}` but no files found.");
                }
            }
        }
        return $starterkit_config;
    }
    /**
     * Gets the Starter Kit version string.
     */
    private static function get_starter_kit_version(Extension $theme, Symfony_Style $io): string
    {
        $source_version = $theme->info['version'] ?? '';
        if ($source_version === '') {
            $confirm = new Confirmation_Question(sprintf('The source theme %s does not have a version specified. This makes tracking changes in the source theme difficult. Are you sure you want to continue?', $theme->get_name()));
            if (!$io->ask_question($confirm)) {
                throw new \RuntimeException('source version could not be determined');
            }
            $source_version = 'unknown-version';
        }
        if ($source_version === 'VERSION') {
            $source_version = \Drupal::VERSION;
        }
        // A version in the generator string like "9.4.0-dev" is not very helpful.
        // When this occurs, generate a version string that points to a commit.
        if (Version_Parser::parse_stability($source_version) === 'dev') {
            $git_check = Process::from_shell_commandline('git --help');
            $git_check->run();
            if ($git_check->get_exit_code()) {
                throw new \RuntimeException(sprintf('The source theme %s has a development version number (%s). Determining a specific commit is not possible because git is not installed. Either install git or use a tagged release to generate a theme.', $theme->get_name(), $source_version));
            }
            // Get the git commit for the source theme.
            $git_get_commit = Process::from_shell_commandline("git rev-list --max-count=1 --abbrev-commit HEAD -C {$theme->get_path()}");
            $git_get_commit->run();
            if (!$git_get_commit->is_successful() || $git_get_commit->get_output() === '') {
                $confirm = new Confirmation_Question(sprintf('The source theme %s has a development version number (%s). Because it is not a git checkout, a specific commit could not be identified. This makes tracking changes in the source theme difficult. Are you sure you want to continue?', $theme->get_name(), $source_version));
                if (!$io->ask_question($confirm)) {
                    throw new \RuntimeException('source version could not be determined');
                }
                $source_version .= '#unknown-commit';
            } else {
                $source_version .= '#' . trim($git_get_commit->get_output());
            }
        }
        return $source_version;
    }
    /**
     * Returns the possible file name patterns.
     */
    private static function name_patterns(string $machine_name, string $label): array
    {
        return ['machine_name' => $machine_name, 'machine_name_camel' => u($machine_name)->camel(), 'machine_name_pascal' => u($machine_name)->camel()->title(), 'machine_name_title' => u($machine_name)->title(), 'label' => $label, 'label_camel' => u($label)->camel(), 'label_pascal' => u($label)->camel()->title(), 'label_title' => u($label)->title()];
    }
}