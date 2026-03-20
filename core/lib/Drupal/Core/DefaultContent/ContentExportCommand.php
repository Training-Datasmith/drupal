<?php

declare (strict_types=1);
namespace Drupal\Core\Default_Content;

use Drupal\Core\Command\Bootable_Command_Trait;
use Drupal\Core\Entity\Content_Entity_Interface;
use Drupal\Core\Entity\Content_Entity_Storage_Interface;
use Drupal\Core\Entity\Entity_Type_Bundle_Info_Interface;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Drupal\Core\File\File_System_Interface;
use Drupal\Core\String_Translation\String_Translation_Trait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
/**
 * Exports content entities in YAML format.
 *
 * @internal
 *    This API is experimental.
 */
final class Content_Export_Command extends Command
{
    use Bootable_Command_Trait;
    use String_Translation_Trait;
    public function __construct(object $class_loader)
    {
        parent::__construct('content:export');
        $this->class_loader = $class_loader;
    }
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->set_description('Exports content entities in YAML format.')->add_argument('entity_type_id', Input_Argument::REQUIRED, 'The entity type to export (e.g., node, taxonomy_term).')->add_argument('entity_id', Input_Argument::OPTIONAL, 'The ID of the entity to export. Will usually be a number.')->add_option('with-dependencies', 'W', Input_Option::VALUE_NONE, 'Recursively export all of the entities referenced by this entity into a directory structure.')->add_option('bundle', 'b', Input_Option::VALUE_REQUIRED | Input_Option::VALUE_IS_ARRAY, 'Only export entities of the specified bundle(s).')->add_option('dir', 'd', Input_Option::VALUE_REQUIRED, 'The path where content should be exported.')->add_usage('node 42')->add_usage('node 3 --with-dependencies --dir=/path/to/content')->add_usage('media --bundle=image --dir=images')->add_usage('taxonomy_term --bundle=tags --bundle=categories --dir=terms');
    }
    /**
     * {@inheritdoc}
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $container = $this->boot()->get_container();
        $entity_type_id = $input->get_argument('entity_type_id');
        $entity_id = $input->get_argument('entity_id');
        $bundles = $input->get_option('bundle');
        $entity_type_manager = $container->get(Entity_Type_Manager_Interface::class);
        if (!$entity_type_manager->has_definition($entity_type_id)) {
            $io->error("The entity type \"{$entity_type_id}\" does not exist.");
            return 1;
        }
        if (!$entity_type_manager->get_definition($entity_type_id)->entity_class_implements(Content_Entity_Interface::class)) {
            $io->error("{$entity_type_id} is not a content entity type.");
            return 1;
        }
        // Confirm that all specified bundles exist.
        if ($bundles) {
            $unknown_bundles = array_diff($bundles, array_keys($container->get(Entity_Type_Bundle_Info_Interface::class)->get_bundle_info($entity_type_id)));
            if ($unknown_bundles) {
                $io->error("These bundles do not exist on the {$entity_type_id} entity type: " . implode(', ', $unknown_bundles));
                return 1;
            }
        }
        $dir = $input->get_option('dir');
        $with_dependencies = $input->get_option('with-dependencies');
        $exporter = $container->get(Exporter::class);
        // If we're going to export multiple entities, or a single entity with its
        // dependencies, require the `--dir` option.
        if (empty($dir) && (empty($entity_id) || $with_dependencies)) {
            throw new RuntimeException('The --dir option is required to export multiple entities, or a single entity with its dependencies.');
        }
        $count = 0;
        $storage = $entity_type_manager->get_storage($entity_type_id);
        foreach ($this->load_entities($storage, $entity_id, $bundles) as $entity) {
            if ($with_dependencies) {
                $count += $exporter->export_with_dependencies($entity, $dir);
            } elseif ($dir) {
                $exporter->export_to_file($entity, $dir);
                $count++;
            } else {
                $io->write((string) $exporter->export($entity));
                return 0;
            }
        }
        // If we were trying to export a specific entity and it didn't get exported,
        // that's an error.
        if ($entity_id && $count === 0) {
            $io->error("{$entity_type_id} {$entity_id} does not exist.");
            if ($bundles) {
                $io->caution('Maybe this entity is not one of the specified bundles: ' . implode(', ', $bundles));
            }
            return 1;
        }
        $file_system = $container->get(File_System_Interface::class);
        $message = (string) $this->format_plural($count, 'One entity was exported to @dir.', '@count entities were exported to @dir.', ['@dir' => $file_system->realpath($dir)]);
        $io->success($message);
        return 0;
    }
    /**
     * Find entities to export and yield them one by one.
     *
     * @param \Drupal\Core\Entity\ContentEntityStorageInterface $storage
     *   The entity storage handler.
     * @param string|int|null $entity_id
     *   The ID of the specific entity to load, or NULL to load all entities
     *   (probably filtered by bundle).
     * @param string[] $bundles
     *   (optional) The bundles to filter by.
     *
     * @return iterable<\Drupal\Core\Entity\ContentEntityInterface>
     *   A generator that yields content entities.
     */
    private function load_entities(Content_Entity_Storage_Interface $storage, string|int|null $entity_id, array $bundles = []): iterable
    {
        $values = [];
        $entity_type = $storage->get_entity_type();
        if ($bundles) {
            $values[$entity_type->get_key('bundle')] = $bundles;
        }
        if ($entity_id) {
            $values[$entity_type->get_key('id')] = $entity_id;
        }
        return $storage->load_by_properties($values);
    }
}