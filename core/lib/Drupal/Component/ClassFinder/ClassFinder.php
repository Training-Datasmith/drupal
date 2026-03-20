<?php

declare (strict_types=1);
namespace Drupal\Component\Class_Finder;

/**
 * A Utility class that uses active autoloaders to find a file for a class.
 */
class Class_Finder implements Class_Finder_Interface
{
    /**
     * {@inheritdoc}
     */
    public function find_file($class)
    {
        $loaders = spl_autoload_functions();
        foreach ($loaders as $loader) {
            if (is_array($loader) && isset($loader[0]) && is_object($loader[0]) && method_exists($loader[0], 'findFile')) {
                $file = call_user_func_array([$loader[0], 'findFile'], [$class]);
                // Different implementations return different empty values. For example,
                // \Composer\Autoload\ClassLoader::findFile() returns FALSE whilst
                // \Drupal\Component\ClassFinder\ClassFinderInterface::findFile()
                // documents that a NULL should be returned.
                if (!empty($file)) {
                    return $file;
                }
            }
        }
        return null;
    }
}