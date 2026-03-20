<?php

declare (strict_types=1);
namespace Drupal\Core\Command;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Connection_Not_Defined_Exception;
use Drupal\Core\Database\Database;
use Drupal\Core\Drupal_Kernel;
use Drupal\Core\Extension\Extension_Discovery;
use Drupal\Core\Extension\Info_Parser_Dynamic;
use Drupal\Core\Site\Settings;
use Drupal\Core\String_Translation\String_Translation_Trait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
/**
 * Installs a Drupal site for local testing/development.
 *
 * @internal
 *   This command makes no guarantee of an API for Drupal extensions.
 */
class Install_Command extends Command
{
    use String_Translation_Trait;
    /**
     * Constructs a new InstallCommand command.
     *
     * @param object $classLoader
     *   The class loader.
     */
    public function __construct(
        /**
         * The class loader.
         */
        protected $class_loader
    )
    {
        parent::__construct('install');
    }
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->set_name('install')->set_description('Installs a Drupal demo site. This is not meant for production and might be too simple for custom development. It is a quick and easy way to get Drupal running.')->add_argument('install-profile-or-recipe', Input_Argument::OPTIONAL, 'Install profile or recipe directory from which to install the site.')->add_option('langcode', null, Input_Option::VALUE_OPTIONAL, 'The language to install the site in.', 'en')->add_option('password', null, Input_Option::VALUE_OPTIONAL, 'The password to use for the site. Defaults to random password.')->add_option('site-name', null, Input_Option::VALUE_OPTIONAL, 'Set the site name.', 'Drupal')->add_usage('demo_umami --langcode fr')->add_usage('standard --site-name QuickInstall')->add_usage('core/recipes/standard --site-name RecipeBuiltSite');
        parent::configure();
    }
    /**
     * {@inheritdoc}
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        if (!extension_loaded('pdo_sqlite')) {
            $io->get_error_style()->error('You must have the pdo_sqlite PHP extension installed. See core/INSTALL.sqlite.txt for instructions.');
            return 1;
        }
        // Change the directory to the Drupal root.
        chdir(dirname(__DIR__, 5));
        // Check whether there is already an installation.
        if ($this->is_drupal_installed()) {
            // Do not fail if the site is already installed so this command can be
            // chained with ServerCommand.
            $output->writeln('<info>Drupal is already installed.</info> If you want to reinstall, remove sites/default/files and sites/default/settings.php.');
            return 0;
        }
        $install_profile_or_recipe = $input->get_argument('install-profile-or-recipe');
        if (!$install_profile_or_recipe) {
            // User did not provide a recipe or install profile.
            $install_profile = $this->select_profile($io);
        } elseif ($this->validate_profile($install_profile_or_recipe)) {
            // User provided an install profile.
            $install_profile = $install_profile_or_recipe;
        } elseif ($this->validate_recipe($install_profile_or_recipe)) {
            // User provided a recipe.
            $recipe = $install_profile_or_recipe;
        } else {
            $error_msg = sprintf("'%s' is not a valid install profile or recipe.", $install_profile_or_recipe);
            // If it does not look like a path make suggestions based upon available
            // profiles.
            if (!str_contains('/', (string) $install_profile_or_recipe)) {
                $alternatives = [];
                foreach (array_keys($this->get_profiles(true, false)) as $profile_name) {
                    $lev = levenshtein($install_profile_or_recipe, $profile_name);
                    if ($lev <= strlen((string) $profile_name) / 4 || str_contains((string) $profile_name, (string) $install_profile_or_recipe)) {
                        $alternatives[] = $profile_name;
                    }
                }
                if (!empty($alternatives)) {
                    $error_msg .= sprintf(" Did you mean '%s'?", implode("' or '", $alternatives));
                }
            }
            $io->get_error_style()->error($error_msg);
            return 1;
        }
        return $this->install($this->class_loader, $io, $install_profile ?? '', $input->get_option('langcode'), $this->get_site_path(), $input->get_option('site-name'), $recipe ?? '', $input->get_option('password'));
    }
    /**
     * Returns whether there is already an existing Drupal installation.
     *
     * @return bool
     *   Returns TRUE if Drupal is installed, FALSE otherwise.
     */
    protected function is_drupal_installed()
    {
        try {
            $kernel = new Drupal_Kernel('prod', $this->class_loader, false);
            $kernel::boot_environment();
            $kernel->set_site_path($this->get_site_path());
            Settings::initialize($kernel->get_app_root(), $kernel->get_site_path(), $this->class_loader);
            $kernel->boot();
        } catch (Connection_Not_Defined_Exception) {
            return false;
        }
        return !empty(Database::get_connection_info());
    }
    /**
     * Installs Drupal with specified installation profile.
     *
     * @param object $class_loader
     *   The class loader.
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     *   The Symfony output decorator.
     * @param string $profile
     *   The installation profile to use.
     * @param string $langcode
     *   The language to install the site in.
     * @param string $site_path
     *   The path to install the site to, like 'sites/default'.
     * @param string $site_name
     *   The site name.
     * @param string $recipe
     *   The recipe to use for installing.
     * @param string|null $password
     *   The password to use for installing.
     *
     * @throws \Exception
     *   Thrown when failing to create the $site_path directory or settings.php.
     *
     * @return int
     *   The command exit status.
     */
    protected function install($class_loader, Symfony_Style $io, $profile, $langcode, string $site_path, $site_name, string $recipe, ?string $password = null): int
    {
        $sqlite_driver_namespace = 'Drupal\sqlite\Driver\Database\sqlite';
        $password ??= Crypt::random_bytes_base64(12);
        $parameters = ['interactive' => false, 'site_path' => $site_path, 'parameters' => ['profile' => $profile, 'langcode' => $langcode], 'forms' => ['install_settings_form' => ['driver' => $sqlite_driver_namespace, $sqlite_driver_namespace => ['database' => $site_path . '/files/.sqlite']], 'install_configure_form' => [
            'site_name' => $site_name,
            'site_mail' => 'drupal@localhost',
            'account' => ['name' => 'admin', 'mail' => 'admin@localhost', 'pass' => ['pass1' => $password, 'pass2' => $password]],
            'enable_update_status_module' => true,
            // \Drupal\Core\Render\Element\Checkboxes::valueCallback() requires
            // NULL instead of FALSE values for programmatic form submissions to
            // disable a checkbox.
            'enable_update_status_emails' => null,
        ]]];
        if ($recipe) {
            $parameters['parameters']['recipe'] = $recipe;
        }
        // Create the directory and settings.php if not there so that the installer
        // works.
        if (!is_dir($site_path)) {
            if ($io->is_verbose()) {
                $io->writeln("Creating directory: {$site_path}");
            }
            if (!mkdir($site_path, 0775)) {
                throw new \RuntimeException("Failed to create directory {$site_path}");
            }
        }
        if (!file_exists("{$site_path}/settings.php")) {
            if ($io->is_verbose()) {
                $io->writeln("Creating file: {$site_path}/settings.php");
            }
            if (!copy('sites/default/default.settings.php', "{$site_path}/settings.php")) {
                throw new \RuntimeException("Copying sites/default/default.settings.php to {$site_path}/settings.php failed.");
            }
        }
        require_once 'core/includes/install.core.inc';
        $progress_bar = $io->create_progress_bar();
        install_drupal($class_loader, $parameters, function ($install_state) use ($progress_bar): void {
            static $started = false;
            if (!$started) {
                $started = true;
                // We've already done 1.
                $progress_bar->set_format("%current%/%max% [%bar%]\n%message%\n");
                $progress_bar->set_message($this->t('Installing @drupal', ['@drupal' => drupal_install_profile_distribution_name()]));
                $tasks = install_tasks($install_state);
                $progress_bar->start(count($tasks) + 1);
            }
            $tasks_to_perform = install_tasks_to_perform($install_state);
            $task = current($tasks_to_perform);
            if (isset($task['display_name'])) {
                $progress_bar->set_message($task['display_name']);
            }
            $progress_bar->advance();
        });
        $success_message = $this->t('Congratulations, you installed @drupal!', ['@drupal' => drupal_install_profile_distribution_name(), '@name' => 'admin', '@pass' => $password], ['langcode' => $langcode]);
        $progress_bar->set_message('<info>' . $success_message . '</info>');
        $progress_bar->display();
        $progress_bar->finish();
        $io->writeln('<info>Username:</info> admin');
        $io->writeln("<info>Password:</info> {$password}");
        return 0;
    }
    /**
     * Gets the site path.
     *
     * Defaults to 'sites/default'. For testing purposes this can be overridden
     * using the DRUPAL_DEV_SITE_PATH environment variable.
     *
     * @return string
     *   The site path to use.
     */
    protected function get_site_path()
    {
        return getenv('DRUPAL_DEV_SITE_PATH') ?: 'sites/default';
    }
    /**
     * Selects the install profile to use.
     *
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     *   Symfony style output decorator.
     *
     * @return string
     *   The selected install profile.
     *
     * @see _install_select_profile()
     * @see \Drupal\Core\Installer\Form\SelectProfileForm
     */
    protected function select_profile(Symfony_Style $io)
    {
        $profiles = $this->get_profiles();
        // If there is a distribution there will be only one profile.
        if (count($profiles) == 1) {
            return key($profiles);
        }
        // Display alphabetically by human-readable name, but always put the core
        // profiles first (if they are present in the filesystem).
        natcasesort($profiles);
        if (isset($profiles['minimal'])) {
            // If the expert ("Minimal") core profile is present, put it in front of
            // any non-core profiles rather than including it with them
            // alphabetically, since the other profiles might be intended to group
            // together in a particular way.
            $profiles = ['minimal' => $profiles['minimal']] + $profiles;
        }
        if (isset($profiles['standard'])) {
            // If the default ("Standard") core profile is present, put it at the very
            // top of the list. This profile will have its radio button pre-selected,
            // so we want it to always appear at the top.
            $profiles = ['standard' => $profiles['standard']] + $profiles;
        }
        reset($profiles);
        return $io->choice('Select an installation profile', $profiles, current($profiles));
    }
    /**
     * Validates a user provided install profile.
     *
     * @param string $install_profile
     *   Install profile to validate.
     *
     * @return bool
     *   TRUE if the profile is valid, FALSE if not.
     */
    protected function validate_profile($install_profile): bool
    {
        // Allow people to install hidden and non-distribution profiles if they
        // supply the argument.
        return array_key_exists($install_profile, $this->get_profiles(true, false));
    }
    /**
     * Validates a user provided recipe.
     *
     * @param string $recipe
     *   The path to the recipe to validate.
     *
     * @return bool
     *   TRUE if the recipe exists, FALSE if not.
     */
    protected function validate_recipe(string $recipe): bool
    {
        // It is impossible to validate a recipe fully at this point because that
        // requires a container.
        if (!is_dir($recipe) || !is_file($recipe . '/recipe.yml')) {
            return false;
        }
        return true;
    }
    /**
     * Gets a list of profiles.
     *
     * @param bool $include_hidden
     *   (optional) Whether to include hidden profiles. Defaults to FALSE.
     * @param bool $auto_select_distributions
     *   (optional) Whether to only return the first distribution found.
     *
     * @return string[]
     *   An array of profile descriptions keyed by the profile machine name.
     */
    protected function get_profiles($include_hidden = false, $auto_select_distributions = true): array
    {
        // Build a list of all available profiles.
        $listing = new Extension_Discovery(getcwd(), false);
        $listing->set_profile_directories([]);
        $profiles = [];
        $info_parser = new Info_Parser_Dynamic(getcwd());
        foreach ($listing->scan('profile') as $profile) {
            $details = $info_parser->parse($profile->get_pathname());
            // Don't show hidden profiles.
            if (!$include_hidden && !empty($details['hidden'])) {
                continue;
            }
            // Determine the name of the profile; default to the internal name if none
            // is specified.
            $name = $details['name'] ?? $profile->get_name();
            $description = $details['description'] ?? $name;
            $profiles[$profile->get_name()] = $description;
            if ($auto_select_distributions && !empty($details['distribution'])) {
                return [$profile->get_name() => $description];
            }
        }
        return $profiles;
    }
}