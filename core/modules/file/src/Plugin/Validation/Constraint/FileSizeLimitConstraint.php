<?php

declare(strict_types=1);

namespace Drupal\file\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * File size max constraint.
 */
#[Constraint(
    id: 'FileSizeLimit',
    label: new TranslatableMarkup('File Size Limit', [], ['context' => 'Validation']),
    type: 'file'
)]
class FileSizeLimitConstraint extends SymfonyConstraint
{
    /**
     * The file limit.
     */
    public int $fileLimit = 0;

    /**
     * The user limit.
     */
    public int $userLimit = 0;

    public function __construct(
        mixed $options = null,
        ?int $fileLimit = null,
        ?int $userLimit = null,
        public string $maxFileSizeMessage = 'The file is %filesize exceeding the maximum file size of %maxsize.',
        public string $diskQuotaMessage = 'The file is %filesize which would exceed your disk quota of %quota.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
        $this->fileLimit = $fileLimit ?? $this->fileLimit;
        $this->userLimit = $userLimit ?? $this->userLimit;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption(): ?string
    {
        return 'fileLimit';
    }

}
