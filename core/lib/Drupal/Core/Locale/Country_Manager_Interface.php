<?php

declare(strict_types=1);

namespace Drupal\Core\Locale;

/**
 * Defines a common interface for country managers.
 */
interface CountryManagerInterface
{
    /**
     * Returns a list of country code => country name pairs.
     *
     * @return array
     *   An array of country code => country name pairs.
     */
    public function getList();

}
