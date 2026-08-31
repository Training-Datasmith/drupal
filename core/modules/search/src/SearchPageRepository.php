<?php

declare(strict_types=1);

namespace Drupal\search;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a repository for Search Page config entities.
 */
class SearchPageRepository implements SearchPageRepositoryInterface
{
    /**
     * The search page storage.
     *
     * @var \Drupal\Core\Entity\EntityStorageInterface
     */
    protected $storage;

    /**
     * Constructs a new SearchPageRepository.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
     *   The config factory.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager.
     */
    public function __construct(protected \Drupal\Core\Config\ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entity_type_manager)
    {
        $this->storage = $entity_type_manager->getStorage('search_page');
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveSearchPages()
    {
        $ids = $this->getQuery()
          ->condition('status', true)
          ->execute();
        return $this->storage->loadMultiple($ids);
    }

    /**
     * {@inheritdoc}
     */
    public function isSearchActive(): bool
    {
        return (bool) $this->getQuery()
          ->condition('status', true)
          ->range(0, 1)
          ->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function getIndexableSearchPages(): array
    {
        return array_filter($this->getActiveSearchPages(), fn (SearchPageInterface $search) => $search->isIndexable());
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultSearchPage()
    {
        // Find all active search pages (without loading them).
        $search_pages = $this->getQuery()
          ->condition('status', true)
          ->execute();

        // If the default page is active, return it.
        $default = $this->configFactory->get('search.settings')->get('default_page');

        // Otherwise, use the first active search page.
        if ($default && is_array($search_pages) && isset($search_pages[$default])) {
            return $default;
        }
        return is_array($search_pages) ? reset($search_pages) : false;
    }

    /**
     * {@inheritdoc}
     */
    public function clearDefaultSearchPage(): void
    {
        $this->configFactory->getEditable('search.settings')->clear('default_page')->save();
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultSearchPage(SearchPageInterface $search_page): void
    {
        $this->configFactory->getEditable('search.settings')->set('default_page', $search_page->id())->save();
        $search_page->enable()->save();
    }

    /**
     * {@inheritdoc}
     */
    public function sortSearchPages($search_pages)
    {
        $entity_type = $this->storage->getEntityType();
        uasort($search_pages, [$entity_type->getClass(), 'sort']);
        return $search_pages;
    }

    /**
     * Returns an entity query instance.
     *
     * @return \Drupal\Core\Entity\Query\QueryInterface
     *   The query instance.
     */
    protected function getQuery()
    {
        return $this->storage->getQuery();
    }

}
