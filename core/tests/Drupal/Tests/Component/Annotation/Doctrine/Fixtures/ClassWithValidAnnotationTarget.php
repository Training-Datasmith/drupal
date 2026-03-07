<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Annotation\Doctrine\Fixtures;

/**
 * @AnnotationTargetClass("Some data")
 */
class ClassWithValidAnnotationTarget
{
    /**
     * @AnnotationTargetPropertyMethod("Some data")
     */
    public $foo;

    /**
     * @AnnotationTargetAll("Some data",name="Some name")
     */
    public $name;

    /**
     * @AnnotationTargetPropertyMethod("Some data",name="Some name")
     */
    public function someFunction()
    {

    }

    /**
     * @AnnotationTargetAll(@AnnotationTargetAnnotation)
     */
    public $nested;

}
