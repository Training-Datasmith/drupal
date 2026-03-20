<?php

declare(strict_types=1);

namespace Drupal\file_test;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileAccessControlHandler;
use Drupal\file\FileAccessFormatterControlHandlerInterface;

/**
 * Defines a class for an alternate file access control handler.
 */
class FileTestAccessControlHandler extends FileAccessControlHandler implements FileAccessFormatterControlHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account)
    {
        \Drupal::state()->set('file_access_formatter_check', true);
        return parent::checkAccess($entity, $operation, $account);
    }

}
