<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
use Drupal\Core\Utility\Table_Sort;
/**
 * Query extender class for tablesort queries.
 */
class Table_Sort_Extender extends Select_Extender
{
    /**
     * {@inheritdoc}
     */
    public function __construct(Select_Interface $query, Connection $connection)
    {
        parent::__construct($query, $connection);
        // Add convenience tag to mark that this is an extended query. We have to
        // do this in the constructor to ensure that it is set before preExecute()
        // gets called.
        $this->add_tag('tablesort');
    }
    /**
     * Order the query based on a header array.
     *
     * @param array $header
     *   Table header array.
     *
     * @return \Drupal\Core\Database\Query\SelectInterface
     *   The called object.
     *
     * @see table.html.twig
     */
    public function order_by_header(array $header): static
    {
        $context = Table_Sort::get_context_from_request($header, \Drupal::request());
        if (!empty($context['sql'])) {
            // Based on code from \Drupal\Core\Database\Connection::escapeTable(),
            // but this can also contain a dot.
            $field = preg_replace('/[^A-Za-z0-9_.]+/', '', (string) $context['sql']);
            // orderBy() will ensure that only ASC/DESC values are accepted, so we
            // don't need to sanitize that here.
            $this->order_by($field, $context['sort']);
        }
        return $this;
    }
}