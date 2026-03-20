<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines the LanguagesCacheContext service, for "per language" caching.
 */
class Languages_Cache_Context implements Calculated_Cache_Context_Interface
{
    /**
     * Constructs a new LanguagesCacheContext service.
     *
     * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
     *   The language manager.
     */
    public function __construct(protected \Drupal\Core\Language\Language_Manager_Interface $language_manager)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Language');
    }
    /**
     * {@inheritdoc}
     *
     * $type can be NULL, or one of the language types supported by the language
     * manager, typically:
     * - LanguageInterface::TYPE_INTERFACE
     * - LanguageInterface::TYPE_CONTENT
     * - LanguageInterface::TYPE_URL
     *
     * @see \Drupal\Core\Language\LanguageManagerInterface::getLanguageTypes()
     *
     * @throws \RuntimeException
     *   In case an invalid language type is specified.
     */
    public function get_context($type = null)
    {
        if ($type === null) {
            $context_parts = [];
            if ($this->language_manager->is_multilingual()) {
                foreach ($this->language_manager->get_language_types() as $type) {
                    $context_parts[] = $this->language_manager->get_current_language($type)->get_id();
                }
            } else {
                $context_parts[] = $this->language_manager->get_current_language()->get_id();
            }
            return implode(',', $context_parts);
        }
        $language_types = $this->language_manager->get_defined_language_types_info();
        if (!isset($language_types[$type])) {
            throw new \RuntimeException(sprintf('The language type "%s" is invalid.', $type));
        }
        return $this->language_manager->get_current_language($type)->get_id();
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata($type = null): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}