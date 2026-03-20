<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Development;

use Drupal\Core\Config\Config_Crud_Event;
use Drupal\Core\Config\Schema\Schema_Incomplete_Exception;
use Drupal\Core\Config\Typed_Config_Manager_Interface;
use Drupal\Core\Messenger\Messenger_Interface;
use Psr\Log\Logger_Interface;
/**
 * Listens to the config save event and warns about invalid schema.
 */
class Lenient_Config_Schema_Checker extends Config_Schema_Checker
{
    /**
     * Constructs the ConfigSchemaChecker object.
     *
     * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_manager
     *   The typed config manager.
     * @param \Drupal\Core\Messenger\MessengerInterface $messenger
     *   The messenger service to display the warning.
     * @param \Psr\Log\LoggerInterface $logger
     *   The logger to save the warning.
     * @param string[] $exclude
     *   An array of config object names that are excluded from schema checking.
     */
    public function __construct(Typed_Config_Manager_Interface $typed_manager, protected readonly Messenger_Interface $messenger, protected readonly Logger_Interface $logger, array $exclude = [])
    {
        parent::__construct($typed_manager, $exclude);
    }
    /**
     * Checks that configuration complies with its schema on config save.
     *
     * @param \Drupal\Core\Config\ConfigCrudEvent $event
     *   The configuration event.
     */
    public function on_config_save(Config_Crud_Event $event): void
    {
        try {
            parent::on_config_save($event);
        } catch (Schema_Incomplete_Exception $exception) {
            $message = sprintf('%s. These errors mean there is configuration that does not comply with its schema. This is not a fatal error, but it is recommended to fix these issues. For more information on configuration schemas, check out <a href="%s">the documentation</a>.', $exception->get_message(), 'https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata');
            $this->messenger->add_warning($message);
            $this->logger->warning($message);
        }
    }
}