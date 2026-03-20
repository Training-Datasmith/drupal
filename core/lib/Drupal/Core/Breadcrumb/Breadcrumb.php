<?php

declare (strict_types=1);
namespace Drupal\Core\Breadcrumb;

use Drupal\Core\Cache\Refinable_Cacheable_Dependency_Interface;
use Drupal\Core\Cache\Refinable_Cacheable_Dependency_Trait;
use Drupal\Core\Link;
use Drupal\Core\Render\Renderable_Interface;
/**
 * Used to return generated breadcrumbs with associated cacheability metadata.
 */
class Breadcrumb implements Renderable_Interface, Refinable_Cacheable_Dependency_Interface
{
    use Refinable_Cacheable_Dependency_Trait;
    /**
     * An ordered list of links for the breadcrumb.
     *
     * @var \Drupal\Core\Link[]
     */
    protected $links = [];
    /**
     * Gets the breadcrumb links.
     *
     * @return \Drupal\Core\Link[]
     *   An ordered list of the links for the breadcrumb.
     */
    public function get_links()
    {
        return $this->links;
    }
    /**
     * Sets the breadcrumb links.
     *
     * @param \Drupal\Core\Link[] $links
     *   The breadcrumb links.
     *
     * @return $this
     *
     * @throws \LogicException
     *   Thrown when setting breadcrumb links after they've already been set.
     */
    public function set_links(array $links): static
    {
        if (!empty($this->links)) {
            throw new \LogicException('Once breadcrumb links are set, only additional breadcrumb links can be added.');
        }
        $this->links = $links;
        return $this;
    }
    /**
     * Appends a link to the end of the ordered list of breadcrumb links.
     *
     * @param \Drupal\Core\Link $link
     *   The link appended to the breadcrumb.
     *
     * @return $this
     */
    public function add_link(Link $link): static
    {
        $this->links[] = $link;
        return $this;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function to_renderable(): array
    {
        $build = ['#cache' => ['contexts' => $this->cache_contexts, 'tags' => $this->cache_tags, 'max-age' => $this->cache_max_age]];
        if (!empty($this->links)) {
            $build += ['#theme' => 'breadcrumb', '#links' => $this->links];
        }
        return $build;
    }
}