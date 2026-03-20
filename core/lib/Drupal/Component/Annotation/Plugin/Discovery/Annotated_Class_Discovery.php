<?php

declare (strict_types=1);
namespace Drupal\Component\Annotation\Plugin\Discovery;

use Drupal\Component\Annotation\Annotation_Interface;
use Drupal\Component\Annotation\Doctrine\Annotation_Registry;
use Drupal\Component\Annotation\Doctrine\Simple_Annotation_Reader;
use Drupal\Component\Annotation\Doctrine\Static_Reflection_Parser;
use Drupal\Component\Annotation\Reflection\Mock_File_Finder;
use Drupal\Component\File_Cache\File_Cache_Factory;
use Drupal\Component\Plugin\Discovery\Discovery_Interface;
use Drupal\Component\Plugin\Discovery\Discovery_Trait;
use Drupal\Component\Utility\Crypt;
/**
 * Defines a discovery mechanism to find annotated plugins in PSR-4 namespaces.
 */
class Annotated_Class_Discovery implements Discovery_Interface
{
    use Discovery_Trait;
    /**
     * The doctrine annotation reader.
     *
     * @var \Doctrine\Common\Annotations\Reader
     */
    protected $annotation_reader;
    /**
     * The file cache object.
     *
     * @var \Drupal\Component\FileCache\FileCacheInterface
     */
    protected $file_cache;
    /**
     * Constructs a new instance.
     *
     * @param array<string, list<string>> $pluginNamespaces
     *   (optional) An array of namespace that may contain plugin implementations.
     *   Defaults to an empty array.
     * @param string $pluginDefinitionAnnotationName
     *   (optional) The name of the annotation that contains the plugin
     *   definition. Defaults to 'Drupal\Component\Annotation\Plugin'.
     * @param string[] $annotationNamespaces
     *   (optional) Additional namespaces to be scanned for annotation classes.
     */
    public function __construct(
        /**
         * The namespaces within which to find plugin classes.
         */
        protected $plugin_namespaces = [],
        /**
         * The name of the annotation that contains the plugin definition.
         *
         * The class corresponding to this name must implement
         * \Drupal\Component\Annotation\AnnotationInterface.
         */
        protected $plugin_definition_annotation_name = \Drupal\Component\Annotation\Plugin::class,
        /**
         * Additional namespaces to be scanned for annotation classes.
         */
        protected array $annotation_namespaces = []
    )
    {
        $file_cache_suffix = str_replace('\\', '_', $this->plugin_definition_annotation_name);
        $file_cache_suffix .= ':' . Crypt::hash_base64(serialize($this->annotation_namespaces));
        $this->file_cache = File_Cache_Factory::get('annotation_discovery:' . $file_cache_suffix);
    }
    /**
     * Gets the used doctrine annotation reader.
     *
     * @return \Doctrine\Common\Annotations\Reader
     *   The annotation reader.
     */
    protected function get_annotation_reader()
    {
        if (!isset($this->annotation_reader)) {
            $this->annotation_reader = new Simple_Annotation_Reader();
            // Add the namespaces from the main plugin annotation, like @EntityType.
            $namespace = substr($this->plugin_definition_annotation_name, 0, strrpos($this->plugin_definition_annotation_name, '\\'));
            $this->annotation_reader->add_namespace($namespace);
            // Register additional namespaces to be scanned for annotations.
            foreach ($this->annotation_namespaces as $namespace) {
                $this->annotation_reader->add_namespace($namespace);
            }
        }
        return $this->annotation_reader;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_definitions(): array
    {
        $definitions = [];
        $reader = $this->get_annotation_reader();
        // Clear the annotation loaders of any previous annotation classes.
        Annotation_Registry::reset();
        // Search for classes within all PSR-4 namespace locations.
        foreach ($this->get_plugin_namespaces() as $namespace => $dirs) {
            foreach ($dirs as $dir) {
                if (file_exists($dir)) {
                    $iterator = new \Recursive_Iterator_Iterator(new \Recursive_Directory_Iterator($dir, \Recursive_Directory_Iterator::SKIP_DOTS));
                    foreach ($iterator as $fileinfo) {
                        if ($fileinfo->get_extension() == 'php') {
                            if ($cached = $this->file_cache->get($fileinfo->get_path_name())) {
                                if (isset($cached['id'])) {
                                    // Explicitly unserialize this to create a new object
                                    // instance.
                                    $definitions[$cached['id']] = unserialize($cached['content']);
                                }
                                continue;
                            }
                            $sub_path = $iterator->get_sub_iterator()->get_sub_path();
                            $sub_path = $sub_path ? str_replace(DIRECTORY_SEPARATOR, '\\', $sub_path) . '\\' : '';
                            $class = $namespace . '\\' . $sub_path . $fileinfo->get_basename('.php');
                            // The filename is already known, so there is no need to find the
                            // file. However, StaticReflectionParser needs a finder, so use a
                            // mock version.
                            $finder = Mock_File_Finder::create($fileinfo->get_path_name());
                            $parser = new Static_Reflection_Parser($class, $finder, true);
                            /** @var \Drupal\Component\Annotation\AnnotationInterface $annotation */
                            if ($annotation = $reader->get_class_annotation($parser->get_reflection_class(), $this->plugin_definition_annotation_name)) {
                                $this->prepare_annotation_definition($annotation, $class);
                                $id = $annotation->get_id();
                                $content = $annotation->get();
                                $definitions[$id] = $content;
                                // Explicitly serialize this to create a new object instance.
                                $this->file_cache->set($fileinfo->get_path_name(), ['id' => $id, 'content' => serialize($content)]);
                            } else {
                                // Store a NULL object, so the file is not parsed again.
                                $this->file_cache->set($fileinfo->get_path_name(), [null]);
                            }
                        }
                    }
                }
            }
        }
        // Don't let annotation loaders pile up.
        Annotation_Registry::reset();
        return $definitions;
    }
    /**
     * Prepares the annotation definition.
     *
     * @param \Drupal\Component\Annotation\AnnotationInterface $annotation
     *   The annotation derived from the plugin.
     * @param string $class
     *   The class used for the plugin.
     */
    protected function prepare_annotation_definition(Annotation_Interface $annotation, $class)
    {
        $annotation->set_class($class);
    }
    /**
     * Gets an array of PSR-4 namespaces to search for plugin classes.
     *
     * @return array<string, list<string>>
     *   The PSR-4 namespaces for the plugin class.
     */
    protected function get_plugin_namespaces()
    {
        return $this->plugin_namespaces;
    }
}