<?php

declare(strict_types=1);

namespace Drupal\user\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\user\UserInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UserName constraint.
 */
class UserNameConstraintValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($items, Constraint $constraint): void
    {
        if (empty($items) || ($items instanceof FieldItemListInterface && $items->isEmpty())) {
            $this->context->addViolation($constraint->emptyMessage);
            return;
        }
        $name = $items instanceof FieldItemListInterface ? $items->first()->value : $items;
        if (str_starts_with((string) $name, ' ')) {
            $this->context->addViolation($constraint->spaceBeginMessage);
        }
        if (str_ends_with((string) $name, ' ')) {
            $this->context->addViolation($constraint->spaceEndMessage);
        }
        if (str_contains((string) $name, '  ')) {
            $this->context->addViolation($constraint->multipleSpacesMessage);
        }
        if (preg_match('/[^\x{80}-\x{F7} a-z0-9@+_.\'-]/i', (string) $name)
          || preg_match(
              // Non-printable ISO-8859-1 + NBSP.
              '/[\x{80}-\x{A0}' .
              // Soft-hyphen.
              '\x{AD}' .
              // Various space characters.
              '\x{2000}-\x{200F}' .
              // Bidirectional text overrides.
              '\x{2028}-\x{202F}' .
              // Various text hinting characters.
              '\x{205F}-\x{206F}' .
              // Byte order mark.
              '\x{FEFF}' .
              // Full-width latin.
              '\x{FF01}-\x{FF60}' .
              // Replacement characters.
              '\x{FFF9}-\x{FFFD}' .
              // NULL byte and control characters.
              '\x{0}-\x{1F}]/u',
              (string) $name
          )
        ) {
            $this->context->addViolation($constraint->invalidMessage);
        }
        if (mb_strlen((string) $name) > UserInterface::USERNAME_MAX_LENGTH) {
            $this->context->addViolation(
                $constraint->tooLongMessage,
                ['%name' => $name, '%max' => UserInterface::USERNAME_MAX_LENGTH]
            );
        }
    }

}
