<?php

declare (strict_types=1);
namespace Drupal\Component\Annotation\Reflection;

use Drupal\Component\Class_Finder\Class_Finder_Interface;
/**
 * Defines a mock file finder that only returns a single filename.
 *
 * This can be used with
 * Drupal\Component\Annotation\Doctrine\StaticReflectionParser if the filename
 * is known and inheritance is not a concern (for example, if only the class
 * annotation is needed).
 */
class Mock_File_Finder implements Class_Finder_Interface
{
    /**
     * The only filename this finder ever returns.
     *
     * @var string
     */
    protected $filename;
    /**
     * {@inheritdoc}
     */
    public function find_file($class)
    {
        return $this->filename;
    }
    /**
     * Creates new mock file finder objects.
     */
    public static function create($filename): static
    {
        $object = new static();
        $object->filename = $filename;
        return $object;
    }
}