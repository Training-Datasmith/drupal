<?php

namespace Drupal\file\Upload;

use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * An uploaded file from an input stream.
 */
final readonly class InputStreamUploadedFile implements UploadedFileInterface {

  /**
   * Creates a new InputStreamUploadedFile.
   */
  public function __construct(
    protected string $clientOriginalName,
    protected string $filename,
    protected string $realPath,
    protected int | false $size,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getClientOriginalName(): string {
    return $this->clientOriginalName;
  }

  /**
   * {@inheritdoc}
   */
  public function getSize(): int {
    return $this->size;
  }

  /**
   * {@inheritdoc}
   */
  public function getRealPath(): string | false {
    return $this->realPath;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilename(): string {
    return $this->filename;
  }

  /**
   * {@inheritdoc}
   */
  public function getPathname(): string {
    throw new \BadMethodCallException(__METHOD__ . ' not implemented');
  }

  /**
   * {@inheritdoc}
   */
  public function validate(ValidatorInterface $validator, array $options = []): ConstraintViolationListInterface {
    return new ConstraintViolationList();
  }

}
