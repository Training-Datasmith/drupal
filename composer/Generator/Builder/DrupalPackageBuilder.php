<?php

declare(strict_types=1);

namespace Drupal\Composer\Generator\Builder;

use Drupal\Composer\Generator\BuilderInterface;

/**
 * Base class that includes helpful utility routine for Drupal builder classes.
 */
abstract class DrupalPackageBuilder implements BuilderInterface
{
    /**
     * DrupalPackageBuilder constructor.
     *
     * @param \Drupal\Composer\Generator\Util\DrupalCoreComposer $drupalCoreInfo
     *   Information about composer.json and composer.lock from current release.
     */
    public function __construct(protected \Drupal\Composer\Generator\Util\DrupalCoreComposer $drupalCoreInfo)
    {
    }

}
