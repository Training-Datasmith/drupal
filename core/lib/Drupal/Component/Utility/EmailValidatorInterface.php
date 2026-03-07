<?php

declare(strict_types=1);

namespace Drupal\Component\Utility;

/**
 * Validates email addresses.
 */
interface EmailValidatorInterface
{
    /**
     * Validates an email address.
     *
     * @param string $email
     *   A string containing an email address.
     *
     * @return bool
     *   TRUE if the address is valid.
     */
    public function isValid($email);

}
