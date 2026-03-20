<?php

declare (strict_types=1);
namespace Drupal\Core\Block;

/**
 * The interface for "title" blocks.
 *
 * A title block shows the title returned by the controller.
 *
 * @ingroup block_api
 *
 * @see \Drupal\Core\Render\Element\PageTitle
 */
interface Title_Block_Plugin_Interface extends Block_Plugin_Interface
{
    /**
     * Sets the title.
     *
     * @param string|array $title
     *   The page title: either a string for plain titles or a render array for
     *   formatted titles.
     */
    public function set_title($title);
}