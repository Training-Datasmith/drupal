<?php

declare(strict_types=1);

namespace Drupal\file\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * A constraint for UploadedFile objects.
 */
class UploadedFileConstraint extends Constraint
{
    /**
     * The upload max size. Defaults to checking the environment.
     */
    public ?int $maxSize = null;

    public function __construct(
        mixed $options = null,
        ?int $maxSize = null,
        public string $uploadIniSizeErrorMessage = 'The file %file could not be saved because it exceeds %maxsize, the maximum allowed size for uploads.',
        public string $uploadFormSizeErrorMessage = 'The file %file could not be saved because it exceeds %maxsize, the maximum allowed size for uploads.',
        public string $uploadPartialErrorMessage = 'The file %file could not be saved because the upload did not complete.',
        public string $uploadNoFileErrorMessage = 'The file %file could not be saved because the upload did not complete.',
        public string $uploadErrorMessage = 'The file %file could not be saved. An unknown error has occurred.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
        $this->maxSize = $maxSize ?? $this->maxSize;
    }

}
