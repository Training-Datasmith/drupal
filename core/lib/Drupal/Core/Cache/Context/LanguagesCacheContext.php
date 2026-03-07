<?php

declare(strict_types=1);

namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Defines the LanguagesCacheContext service, for "per language" caching.
 */
class LanguagesCacheContext implements CalculatedCacheContextInterface
{
    /**
     * Constructs a new LanguagesCacheContext service.
     *
     * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
     *   The language manager.
     */
    public function __construct(protected \Drupal\Core\Language\LanguageManagerInterface $languageManager)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function getLabel()
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
    public function getContext($type = null)
    {
        if ($type === null) {
            $context_parts = [];
            if ($this->languageManager->isMultilingual()) {
                foreach ($this->languageManager->getLanguageTypes() as $type) {
                    $context_parts[] = $this->languageManager->getCurrentLanguage($type)->getId();
                }
            } else {
                $context_parts[] = $this->languageManager->getCurrentLanguage()->getId();
            }
            return implode(',', $context_parts);
        }
        $language_types = $this->languageManager->getDefinedLanguageTypesInfo();
        if (!isset($language_types[$type])) {
            throw new \RuntimeException(sprintf('The language type "%s" is invalid.', $type));
        }
        return $this->languageManager->getCurrentLanguage($type)->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheableMetadata($type = null): \Drupal\Core\Cache\CacheableMetadata
    {
        return new CacheableMetadata();
    }

}
