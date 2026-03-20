<?php

declare (strict_types=1);
namespace Drupal\Component\Utility;

use Egulias\Email_Validator\Email_Validator as EmailValidatorUtility;
use Egulias\Email_Validator\Validation\Email_Validation;
use Egulias\Email_Validator\Validation\Rfc_Validation;
/**
 * Validates email addresses.
 */
class Email_Validator extends Email_Validator_Utility implements Email_Validator_Interface
{
    /**
     * Validates an email address.
     *
     * @param string $email
     *   A string containing an email address.
     * @param \Egulias\EmailValidator\Validation\EmailValidation|null $email_validation
     *   This argument is ignored. If it is supplied an error will be triggered.
     *   See https://www.drupal.org/node/2997196.
     *
     * @return bool
     *   TRUE if the address is valid.
     */
    public function is_valid($email, ?Email_Validation $email_validation = null)
    {
        if ($email_validation) {
            throw new \BadMethodCallException('Calling \Drupal\Component\Utility\EmailValidator::isValid() with the second argument is not supported. See https://www.drupal.org/node/2997196');
        }
        return parent::is_valid($email, new Rfc_Validation());
    }
}