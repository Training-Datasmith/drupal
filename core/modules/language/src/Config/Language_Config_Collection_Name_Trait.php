<?php

declare(strict_types=1);

namespace Drupal\language\Config;

/**
 * Provides a common trait for working with language override collection names.
 */
trait LanguageConfigCollectionNameTrait
{
    /**
     * Creates a configuration collection name based on a language code.
     *
     * @param string $langcode
     *   The language code.
     *
     * @return string
     *   The configuration collection name for a language code.
     */
    protected function createConfigCollectionName(string $langcode): string
    {
        return 'language.' . $langcode;
    }

    /**
     * Converts a configuration collection name to a language code.
     *
     * @param string $collection
     *   The configuration collection name.
     *
     * @return string
     *   The language code of the collection.
     *
     * @throws \InvalidArgumentException
     *   Exception thrown if the provided collection name is not in the format
     *   "language.LANGCODE".
     *
     * @see self::createConfigCollectionName()
     */
    protected function getLangcodeFromCollectionName($collection): string
    {
        preg_match('/^language\.(.*)$/', $collection, $matches);
        if (!isset($matches[1])) {
            throw new \InvalidArgumentException("'$collection' is not a valid language override collection");
        }
        return $matches[1];
    }

}
