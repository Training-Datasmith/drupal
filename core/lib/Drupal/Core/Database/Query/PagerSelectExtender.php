<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
/**
 * Query extender for pager queries.
 *
 * This is the "default" pager mechanism.  It creates a paged query with a fixed
 * number of entries per page.
 *
 * When adding this extender along with other extenders, be sure to add
 * PagerSelectExtender last, so that its range and count are based on the full
 * query.
 */
class Pager_Select_Extender extends Select_Extender
{
    /**
     * The number of elements per page to allow.
     *
     * @var int
     */
    protected $limit = 10;
    /**
     * The unique ID of this pager on this page.
     *
     * @var int
     */
    protected $element;
    /**
     * The count query that will be used for this pager.
     *
     * @var \Drupal\Core\Database\Query\SelectInterface
     */
    protected $custom_count_query = false;
    /**
     * Constructs a PagerSelectExtender object.
     *
     * @param \Drupal\Core\Database\Query\SelectInterface $query
     *   Select query object.
     * @param \Drupal\Core\Database\Connection $connection
     *   Database connection object.
     */
    public function __construct(Select_Interface $query, Connection $connection)
    {
        parent::__construct($query, $connection);
        // Add pager tag. Do this here to ensure that it is always added before
        // preExecute() is called.
        $this->add_tag('pager');
    }
    /**
     * Override the execute method.
     *
     * Before we run the query, we need to add pager-based range() instructions
     * to it.
     */
    public function execute()
    {
        // By calling preExecute() here, we force it to preprocess the extender
        // object rather than just the base query object. That means
        // hook_query_alter() gets access to the extended object.
        if (!$this->pre_execute($this)) {
            return null;
        }
        // A NULL limit is the "kill switch" for pager queries.
        if (empty($this->limit)) {
            return;
        }
        $this->ensure_element();
        $total_items = $this->get_count_query()->execute()->fetch_field();
        $pager = $this->connection->get_pager_manager()->create_pager($total_items, $this->limit, $this->element);
        $this->range($pager->get_current_page() * $this->limit, $this->limit);
        // Now that we've added our pager-based range instructions, run the query
        // normally.
        return $this->query->execute();
    }
    /**
     * Ensure that there is an element associated with this query.
     *
     * After running this method, access $this->element to get the element for
     * this query.
     */
    protected function ensure_element()
    {
        if (!isset($this->element)) {
            $this->element($this->connection->get_pager_manager()->get_max_pager_element_id() + 1);
        }
    }
    /**
     * Specify the count query object to use for this pager.
     *
     * You will rarely need to specify a count query directly.  If not specified,
     * one is generated off of the pager query itself.
     *
     * @param \Drupal\Core\Database\Query\SelectInterface $query
     *   The count query object. It must return a single row with a single column,
     *   which is the total number of records.
     */
    public function set_count_query(Select_Interface $query): void
    {
        $this->custom_count_query = $query;
    }
    /**
     * Retrieve the count query for this pager.
     *
     * The count query may be specified manually or, by default, taken from the
     * query we are extending.
     *
     * @return \Drupal\Core\Database\Query\SelectInterface
     *   A count query object.
     */
    public function get_count_query()
    {
        if ($this->custom_count_query) {
            return $this->custom_count_query;
        }
        return $this->query->count_query();
    }
    /**
     * Specify the maximum number of elements per page for this query.
     *
     * The default if not specified is 10 items per page.
     *
     * @param int|false $limit
     *   An integer specifying the number of elements per page. If passed a false
     *   value (FALSE, 0, NULL), the pager is disabled.
     */
    public function limit($limit = 10): static
    {
        $this->limit = $limit;
        return $this;
    }
    /**
     * Specify the element ID for this pager query.
     *
     * The element is used to differentiate different pager queries on the same
     * page so that they may be operated independently.  If you do not specify an
     * element, every pager query on the page will get a unique element.  If for
     * whatever reason you want to explicitly define an element for a given query,
     * you may do so here.
     *
     * Note that no collision detection is done when setting an element ID
     * explicitly, so it is possible for two pagers to end up using the same ID
     * if both are set explicitly.
     *
     * @param int $element
     *   Element ID that is used to differentiate different pager queries.
     */
    public function element($element): static
    {
        $this->element = $element;
        $this->connection->get_pager_manager()->reserve_pager_element_id($this->element);
        return $this;
    }
    /**
     * Gets the element ID for this pager query.
     *
     * The element is used to differentiate different pager queries on the same
     * page so that they may be operated independently.
     *
     * @return int
     *   Element ID that is used to differentiate between different pager
     *   queries.
     */
    public function get_element(): int
    {
        $this->ensure_element();
        return $this->element;
    }
}