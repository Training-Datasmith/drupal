<?php

declare (strict_types=1);
namespace Drupal\Component\Utility;

/**
 * Validates email addresses.
 */
interface Email_Validator_Interface
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
    public function is_valid($email);
}