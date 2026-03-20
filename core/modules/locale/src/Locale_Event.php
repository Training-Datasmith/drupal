<?php

declare(strict_types=1);

namespace Drupal\locale;

use Drupal\Component\EventDispatcher\Event;

/**
 * Defines a Locale event.
 */
class LocaleEvent extends Event
{
    /**
     * Constructs a new LocaleEvent.
     *
     * @param array $langCodes
     *   Language codes for updated translations.
     * @param array $lids
     *   (optional) List of string identifiers that have been updated / created.
     */
    public function __construct(
        /**
         * The list of Language codes for updated translations.
         */
        protected array $langCodes,
        /**
         * List of string identifiers that have been updated / created.
         */
        protected array $lids = []
    ) {
    }

    /**
     * Returns the language codes.
     *
     * @return string[]
     *   $langCodes
     */
    public function getLangCodes()
    {
        return $this->langCodes;
    }

    /**
     * Returns the string identifiers.
     *
     * @return array
     *   $lids
     */
    public function getLids(): array
    {
        return $this->lids;
    }

}
