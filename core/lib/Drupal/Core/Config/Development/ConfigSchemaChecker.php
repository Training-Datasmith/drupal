<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Development;

use Drupal\Component\Render\Formattable_Markup;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\Config_Crud_Event;
use Drupal\Core\Config\Config_Events;
use Drupal\Core\Config\Schema\Schema_Check_Trait;
use Drupal\Core\Config\Schema\Schema_Incomplete_Exception;
use Drupal\Core\Config\Storage_Interface;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
/**
 * Listens to the config save event and validates schema.
 *
 * If tests have the $strictConfigSchema property set to TRUE this event
 * listener will be added to the container and throw exceptions if configuration
 * is invalid.
 *
 * @see \Drupal\KernelTests\KernelTestBase::register()
 * @see \Drupal\Core\Test\FunctionalTestSetupTrait::prepareSettings()
 */
class Config_Schema_Checker implements Event_Subscriber_Interface
{
    use Schema_Check_Trait;
    /**
     * An array of config checked already. Keyed by config name and a checksum.
     *
     * @var array
     */
    protected $checked = [];
    /**
     * Constructs the ConfigSchemaChecker object.
     *
     * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedManager
     *   The typed config manager.
     * @param string[] $exclude
     *   An array of config object names that are excluded from schema checking.
     * @param bool $validateConstraints
     *   Determines if constraints will be validated. If TRUE, constraint
     *   validation errors will be added to the errors found by
     *   SchemaCheckTrait::checkConfigSchema().
     */
    public function __construct(
        protected \Drupal\Core\Config\Typed_Config_Manager_Interface $typed_manager,
        /**
         * An array of config object names that are excluded from schema checking.
         */
        protected array $exclude = [],
        private readonly bool $validate_constraints = false
    )
    {
    }
    /**
     * Checks that configuration complies with its schema on config save.
     *
     * @param \Drupal\Core\Config\ConfigCrudEvent $event
     *   The configuration event.
     *
     * @throws \Drupal\Core\Config\Schema\SchemaIncompleteException
     *   Exception thrown when configuration does not match its schema.
     */
    public function on_config_save(Config_Crud_Event $event): void
    {
        // Only validate configuration if in the default collection. Other
        // collections may have incomplete configuration (for example language
        // overrides only). These are not valid in themselves.
        $saved_config = $event->get_config();
        if ($saved_config->get_storage()->get_collection_name() != Storage_Interface::DEFAULT_COLLECTION) {
            return;
        }
        $name = $saved_config->get_name();
        $data = $saved_config->get();
        $checksum = Crypt::hash_base64(serialize($data));
        if (!in_array($name, $this->exclude) && !isset($this->checked[$name . ':' . $checksum])) {
            $this->checked[$name . ':' . $checksum] = true;
            $errors = $this->check_config_schema($this->typed_manager, $name, $data, $this->validate_constraints);
            if ($errors === false) {
                throw new Schema_Incomplete_Exception("No schema for {$name}");
            }
            if (is_array($errors)) {
                $text_errors = [];
                foreach ($errors as $key => $error) {
                    $text_errors[] = new Formattable_Markup('@key @error', ['@key' => $key, '@error' => $error]);
                }
                throw new Schema_Incomplete_Exception("Schema errors for {$name} with the following errors: " . implode(', ', $text_errors));
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public static function get_subscribed_events(): array
    {
        $events[Config_Events::SAVE][] = ['onConfigSave', 255];
        return $events;
    }
}