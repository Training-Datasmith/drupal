<?php

declare(strict_types=1);

namespace Drupal\responsive_image\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\responsive_image\ResponsiveImageStyleForm;
use Drupal\responsive_image\ResponsiveImageStyleInterface;
use Drupal\responsive_image\ResponsiveImageStyleListBuilder;

/**
 * Defines the responsive image style entity.
 */
#[ConfigEntityType(
    id: 'responsive_image_style',
    label: new TranslatableMarkup('Responsive image style'),
    label_collection: new TranslatableMarkup('Responsive image styles'),
    label_singular: new TranslatableMarkup('responsive image style'),
    label_plural: new TranslatableMarkup('responsive image styles'),
    config_prefix: 'styles',
    entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
    handlers: [
    'list_builder' => ResponsiveImageStyleListBuilder::class,
    'form' => [
      'edit' => ResponsiveImageStyleForm::class,
      'add' => ResponsiveImageStyleForm::class,
      'delete' => EntityDeleteForm::class,
      'duplicate' => ResponsiveImageStyleForm::class,
    ],
  ],
    links: [
    'edit-form' => '/admin/config/media/responsive-image-style/{responsive_image_style}',
    'duplicate-form' => '/admin/config/media/responsive-image-style/{responsive_image_style}/duplicate',
    'delete-form' => '/admin/config/media/responsive-image-style/{responsive_image_style}/delete',
    'collection' => '/admin/config/media/responsive-image-style',
  ],
    admin_permission: 'administer responsive images',
    label_count: [
    'singular' => '@count responsive image style',
    'plural' => '@count responsive image styles',
  ],
    config_export: [
    'id',
    'label',
    'image_style_mappings',
    'breakpoint_group',
    'fallback_image_style',
  ],
)]
class ResponsiveImageStyle extends ConfigEntityBase implements ResponsiveImageStyleInterface
{
    /**
     * The responsive image ID (machine name).
     *
     * @var string
     */
    protected $id;

    /**
     * The responsive image label.
     *
     * @var string
     */
    protected $label;

    /**
     * The image style mappings.
     *
     * Each image style mapping array contains the following keys:
     *   - image_mapping_type: Either 'image_style' or 'sizes'.
     *   - image_mapping:
     *     - If image_mapping_type is 'image_style', the image style ID (a
     *       string).
     *     - If image_mapping_type is 'sizes', an array with following keys:
     *       - sizes: The value for the 'sizes' attribute.
     *       - sizes_image_styles: The image styles to use for the 'srcset'
     *         attribute.
     *   - breakpoint_id: The breakpoint ID for this image style mapping.
     *   - multiplier: The multiplier for this image style mapping.
     *
     * @var array
     */
    protected $image_style_mappings = [];

    /**
     * @var array
     */
    protected $keyedImageStyleMappings;

    /**
     * The responsive image breakpoint group.
     *
     * @var string
     */
    protected $breakpoint_group = '';

    /**
     * The fallback image style.
     *
     * @var string
     */
    protected $fallback_image_style = '';

    /**
     * {@inheritdoc}
     */
    public function __construct(array $values, $entity_type_id = 'responsive_image_style')
    {
        parent::__construct($values, $entity_type_id);
    }

    /**
     * {@inheritdoc}
     */
    public function addImageStyleMapping($breakpoint_id, $multiplier, array $image_style_mapping): static
    {
        // If there is an existing mapping, overwrite it.
        foreach ($this->image_style_mappings as &$mapping) {
            if ($mapping['breakpoint_id'] === $breakpoint_id && $mapping['multiplier'] === $multiplier) {
                $mapping = $image_style_mapping + [
                  'breakpoint_id' => $breakpoint_id,
                  'multiplier' => $multiplier,
                ];
                $this->sortMappings();
                return $this;
            }
        }
        $this->image_style_mappings[] = $image_style_mapping + [
          'breakpoint_id' => $breakpoint_id,
          'multiplier' => $multiplier,
        ];
        $this->sortMappings();
        return $this;
    }

    /**
     * Sort mappings by breakpoint ID and multiplier.
     */
    protected function sortMappings(): void
    {
        $this->keyedImageStyleMappings = null;
        $breakpoints = \Drupal::service('breakpoint.manager')->getBreakpointsByGroup($this->getBreakpointGroup());
        if (empty($breakpoints)) {
            return;
        }
        usort($this->image_style_mappings, static function (array $a, array $b) use ($breakpoints): int {
            $breakpoint_a = $breakpoints[$a['breakpoint_id']] ?? null;
            $breakpoint_b = $breakpoints[$b['breakpoint_id']] ?? null;
            $first = ((float) mb_substr((string) $a['multiplier'], 0, -1)) * 100;
            $second = ((float) mb_substr((string) $b['multiplier'], 0, -1)) * 100;
            return [$breakpoint_b ? $breakpoint_b->getWeight() : 0, $first] <=> [$breakpoint_a ? $breakpoint_a->getWeight() : 0, $second];
        });
    }

    /**
     * {@inheritdoc}
     */
    public function hasImageStyleMappings(): bool
    {
        $mappings = $this->getKeyedImageStyleMappings();
        return !empty($mappings);
    }

    /**
     * {@inheritdoc}
     */
    public function getKeyedImageStyleMappings()
    {
        if (!$this->keyedImageStyleMappings) {
            $this->keyedImageStyleMappings = [];
            foreach ($this->image_style_mappings as $mapping) {
                if (!static::isEmptyImageStyleMapping($mapping)) {
                    $this->keyedImageStyleMappings[$mapping['breakpoint_id']][$mapping['multiplier']] = $mapping;
                }
            }
        }
        return $this->keyedImageStyleMappings;
    }

    /**
     * {@inheritdoc}
     */
    public function getImageStyleMappings()
    {
        return $this->image_style_mappings;
    }

    /**
     * {@inheritdoc}
     */
    public function setBreakpointGroup($breakpoint_group): static
    {
        // If the breakpoint group is changed then the image style mappings are
        // invalid.
        if ($breakpoint_group !== $this->breakpoint_group) {
            $this->removeImageStyleMappings();
        }
        $this->breakpoint_group = $breakpoint_group;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getBreakpointGroup()
    {
        return $this->breakpoint_group;
    }

    /**
     * {@inheritdoc}
     */
    public function setFallbackImageStyle($fallback_image_style): static
    {
        $this->fallback_image_style = $fallback_image_style;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFallbackImageStyle()
    {
        return $this->fallback_image_style;
    }

    /**
     * {@inheritdoc}
     */
    public function removeImageStyleMappings(): static
    {
        $this->image_style_mappings = [];
        $this->keyedImageStyleMappings = null;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function calculateDependencies(): static
    {
        parent::calculateDependencies();
        $providers = \Drupal::service('breakpoint.manager')->getGroupProviders($this->breakpoint_group);
        foreach ($providers as $provider => $type) {
            $this->addDependency($type, $provider);
        }
        // Extract all the styles from the image style mappings.
        $styles = ImageStyle::loadMultiple($this->getImageStyleIds());
        array_walk($styles, function (ConfigEntityInterface $style): void {
            $this->addDependency('config', $style->getConfigDependencyName());
        });
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function isEmptyImageStyleMapping(array $image_style_mapping): bool
    {
        if (!empty($image_style_mapping)) {
            switch ($image_style_mapping['image_mapping_type']) {
                case 'sizes':
                    // The image style mapping must have a sizes attribute defined and one
                    // or more image styles selected.
                    if ($image_style_mapping['image_mapping']['sizes'] && $image_style_mapping['image_mapping']['sizes_image_styles']) {
                        return false;
                    }
                    break;

                case 'image_style':
                    // The image style mapping must have an image style selected.
                    if ($image_style_mapping['image_mapping']) {
                        return false;
                    }
                    break;
            }
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getImageStyleMapping($breakpoint_id, $multiplier)
    {
        $map = $this->getKeyedImageStyleMappings();
        if (isset($map[$breakpoint_id][$multiplier])) {
            return $map[$breakpoint_id][$multiplier];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getImageStyleIds(): array
    {
        $image_styles = [$this->getFallbackImageStyle()];
        foreach ($this->getImageStyleMappings() as $image_style_mapping) {
            // Only image styles of non-empty mappings should be loaded.
            if (!$this::isEmptyImageStyleMapping($image_style_mapping)) {
                switch ($image_style_mapping['image_mapping_type']) {
                    case 'image_style':
                        $image_styles[] = $image_style_mapping['image_mapping'];
                        break;

                    case 'sizes':
                        $image_styles = array_merge($image_styles, $image_style_mapping['image_mapping']['sizes_image_styles']);
                        break;
                }
            }
        }
        return array_values(array_filter(array_unique($image_styles)));
    }

}
