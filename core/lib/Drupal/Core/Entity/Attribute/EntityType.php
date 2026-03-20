<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\String_Translation\Translatable_Markup;
/**
 * Defines an entity type for plugin discovery.
 *
 * Entity type plugins use an object-based attribute method. The attribute
 * properties of entity types are found on \Drupal\Core\Entity\EntityType and
 * are accessed using get/set methods defined in
 * \Drupal\Core\Entity\EntityTypeInterface.
 *
 * @ingroup entity_api
 *
 * @see \Drupal\Core\Entity\EntityType
 * @see \Drupal\Core\Entity\ContentEntityTypeInterface
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Entity_Type extends Plugin
{
    public function __construct(
        public readonly string $id,
        public readonly ?Translatable_Markup $label = null,
        public readonly ?Translatable_Markup $label_collection = null,
        public readonly ?Translatable_Markup $label_singular = null,
        public readonly ?Translatable_Markup $label_plural = null,
        public readonly string $entity_type_class = \Drupal\Core\Entity\Entity_Type::class,
        public readonly string $group = 'default',
        public readonly Translatable_Markup $group_label = new Translatable_Markup('Other', [], ['context' => 'Entity type group']),
        public readonly bool $static_cache = true,
        public readonly bool $render_cache = true,
        public readonly bool $persistent_cache = true,
        protected readonly array $entity_keys = [],
        protected readonly array $handlers = [],
        protected readonly array $links = [],
        public readonly ?string $admin_permission = null,
        public readonly ?string $collection_permission = null,
        public readonly string $permission_granularity = 'entity_type',
        public readonly ?string $bundle_entity_type = null,
        public readonly ?string $bundle_of = null,
        public readonly ?Translatable_Markup $bundle_label = null,
        public readonly ?string $base_table = null,
        public readonly ?string $data_table = null,
        public readonly ?string $revision_table = null,
        public readonly ?string $revision_data_table = null,
        public readonly bool $internal = false,
        public readonly bool $translatable = false,
        public readonly bool $show_revision_ui = false,
        public readonly array $label_count = [],
        /**
         * A callable that can be used to provide the entity URI.
         *
         * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use link
         *   templates or a route provider to specify entity URIs.
         *
         * @see https://www.drupal.org/node/3575062
         */
        public readonly ?string $uri_callback = null,
        public readonly ?string $field_ui_base_route = null,
        public readonly bool $common_reference_target = false,
        public readonly array $list_cache_contexts = [],
        public readonly array $list_cache_tags = [],
        public readonly array $constraints = [],
        public readonly array $additional = []
    )
    {
        // @phpstan-ignore property.deprecated
        if ($this->uri_callback !== null) {
            @trigger_error('The "uri_callback" property on entity types is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use link templates or a route provider to specify entity URIs. See https://www.drupal.org/node/3575062', E_USER_DEPRECATED);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get(): array|object
    {
        // Use the specified entity type class, and remove it before instantiating.
        $class = $this->entity_type_class;
        $values = array_filter(get_object_vars($this) + ['class' => $this->get_class(), 'provider' => $this->get_provider()], fn($value, $key) => !($value === null && ($key === 'deriver' || $key === 'provider' || $key == 'entity_type_class')), ARRAY_FILTER_USE_BOTH);
        return new $class($values);
    }
}