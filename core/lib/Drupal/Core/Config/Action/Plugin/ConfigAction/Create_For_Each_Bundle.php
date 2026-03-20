<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Action\Plugin\Config_Action;

use Drupal\Component\Plugin\Exception\Invalid_Plugin_Definition_Exception;
use Drupal\Core\Config\Action\Attribute\Config_Action;
use Drupal\Core\Config\Action\Config_Action_Manager;
use Drupal\Core\Config\Action\Config_Action_Plugin_Interface;
use Drupal\Core\Config\Action\Plugin\Config_Action\Deriver\Create_For_Each_Bundle_Deriver;
use Drupal\Core\Config\Config_Manager_Interface;
use Drupal\Core\Plugin\Container_Factory_Plugin_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * Creates config entities for each bundle of a particular entity type.
 *
 * An example of using this in a recipe's config actions would be:
 * @code
 * node.type.*:
 *   createForEach:
 *     language.content_settings.node.%bundle:
 *       target_entity_type_id: node
 *       target_bundle: %bundle
 *     image.style.node_%bundle_big:
 *       label: 'Big images for %label content'
 * @endcode
 * This will create two entities for each existing content type: a content
 * language settings entity, and an image style. For example, for a content type
 * called `blog`, this will create `language.content_settings.node.blog` and
 * `image.style.node_blog_big`, with the given values. The `%bundle` and
 * `%label` placeholders will be replaced with the ID and label of the content
 * type, respectively.
 *
 * @internal
 *   This API is experimental.
 */
#[Config_Action(id: 'create_for_each_bundle', admin_label: new Translatable_Markup('Create entities for each bundle of an entity type'), deriver: Create_For_Each_Bundle_Deriver::class)]
final readonly class Create_For_Each_Bundle implements Config_Action_Plugin_Interface, Container_Factory_Plugin_Interface
{
    /**
     * The placeholder which is replaced with the ID of the current bundle.
     *
     * @var string
     */
    private const BUNDLE_PLACEHOLDER = '%bundle';
    /**
     * The placeholder which is replaced with the label of the current bundle.
     *
     * @var string
     */
    private const LABEL_PLACEHOLDER = '%label';
    public function __construct(private Config_Manager_Interface $config_manager, private string $create_action, private Config_Action_Manager $config_action_manager)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container, array $configuration, $plugin_id, $plugin_definition): static
    {
        // If there are no bundle entity types, this plugin should not be usable.
        if (empty($plugin_definition['entity_types'])) {
            throw new Invalid_Plugin_Definition_Exception($plugin_id, "The {$plugin_id} config action must be restricted to entity types that are bundles of another entity type.");
        }
        return new static($container->get(Config_Manager_Interface::class), $plugin_definition['create_action'], $container->get('plugin.manager.config_action'));
    }
    /**
     * {@inheritdoc}
     */
    public function apply(string $config_name, mixed $value): void
    {
        assert(is_array($value));
        $bundle = $this->config_manager->load_config_entity_by_name($config_name);
        assert(is_object($bundle));
        $value = static::replace_placeholders($value, [static::BUNDLE_PLACEHOLDER => $bundle->id(), static::LABEL_PLACEHOLDER => $bundle->label()]);
        foreach ($value as $name => $values) {
            // Invoke the actual create action via the config action manager, so that
            // the created entity will be validated.
            $this->config_action_manager->apply_action('entity_create:' . $this->create_action, $name, $values);
        }
    }
    /**
     * Replaces placeholders recursively.
     *
     * @param mixed $data
     *   The data to process. If this is an array, it'll be processed recursively.
     * @param array $replacements
     *   An array whose keys are the placeholders to replace in the data, and
     *   whose values are the the replacements. Normally this will only mention
     *   the `%bundle` and `%label` placeholders. If $data is an array, the only
     *   placeholder that is replaced in the array's keys is `%bundle`.
     *
     * @return mixed
     *   The given $data, with the `%bundle` and `%label` placeholders replaced.
     */
    private static function replace_placeholders(mixed $data, array $replacements): mixed
    {
        assert(array_key_exists(static::BUNDLE_PLACEHOLDER, $replacements));
        if (is_string($data)) {
            return str_replace(array_keys($replacements), $replacements, $data);
        }
        if (!is_array($data)) {
            return $data;
        }
        foreach ($data as $old_key => $value) {
            $value = static::replace_placeholders($value, $replacements);
            // Only replace the `%bundle` placeholder in array keys.
            // Non-string keys cannot contain placeholders.
            if (is_string($old_key)) {
                $new_key = str_replace(static::BUNDLE_PLACEHOLDER, $replacements[static::BUNDLE_PLACEHOLDER], $old_key);
                unset($data[$old_key]);
                $data[$new_key] = $value;
            }
        }
        return $data;
    }
}