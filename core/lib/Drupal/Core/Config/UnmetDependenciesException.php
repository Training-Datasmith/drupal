<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Component\Render\Formattable_Markup;
use Drupal\Core\String_Translation\Translation_Interface;
/**
 * An exception thrown if configuration has unmet dependencies.
 */
class Unmet_Dependencies_Exception extends Config_Exception
{
    /**
     * A list of configuration objects that have unmet dependencies.
     *
     * @var array
     * The list is keyed by the config object name, and the value is an array of
     * the missing dependencies:
     * @code
     *
     * self::configObjects = [
     *   config_object_name => [
     *     'missing_dependency_1',
     *     'missing_dependency_2',
     *   ]
     * ];
     *
     * @endcode
     */
    protected $config_objects = [];
    /**
     * The name of the extension that is being installed.
     *
     * @var string
     */
    protected $extension;
    /**
     * Gets the list of configuration objects that have unmet dependencies.
     *
     * @return array
     *   A list of configuration objects that have unmet dependencies, keyed by
     *   object name, with the value being a list of the unmet dependencies.
     */
    public function get_config_objects()
    {
        return $this->config_objects;
    }
    /**
     * Gets the name of the extension that is being installed.
     *
     * @return string
     *   The name of the extension that is being installed.
     */
    public function get_extension()
    {
        return $this->extension;
    }
    /**
     * Gets a translated message from the exception.
     *
     * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
     *   The string translation service.
     * @param string $extension
     *   The name of the extension that is being installed.
     *
     * @return string
     *   The translated exception message.
     */
    public function get_translated_message(Translation_Interface $string_translation, $extension)
    {
        return $string_translation->translate('Unable to install %extension due to unmet dependencies: %config_names', ['%config_names' => static::format_config_object_list($this->config_objects), '%extension' => $extension]);
    }
    /**
     * Creates an exception for an extension and a list of configuration objects.
     *
     * @param string $extension
     *   The name of the extension that is being installed.
     * @param array $config_objects
     *   A list of configuration keyed by configuration name, with unmet
     *   dependencies as the value.
     *
     * @return \Drupal\Core\Config\PreExistingConfigException
     *   An exception for the extension with a list of configuration objects.
     */
    public static function create($extension, array $config_objects): static
    {
        $message = new Formattable_Markup('Configuration objects provided by %extension have unmet dependencies: %config_names', ['%config_names' => static::format_config_object_list($config_objects), '%extension' => $extension]);
        $e = new static($message);
        $e->config_objects = $config_objects;
        $e->extension = $extension;
        return $e;
    }
    /**
     * Formats a list of configuration objects.
     *
     * @param array $config_objects
     *   A list of configuration object names that have unmet dependencies.
     *
     * @return string
     *   The imploded config_objects, formatted in an easy to read string.
     */
    protected static function format_config_object_list(array $config_objects): string
    {
        $list = [];
        foreach ($config_objects as $config_object => $missing_dependencies) {
            $list[] = $config_object . ' (' . implode(', ', $missing_dependencies) . ')';
        }
        return implode(', ', $list);
    }
}