<?php

declare(strict_types=1);

namespace Drupal\toolbar\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Defines an AJAX command that sets the toolbar subtrees.
 */
class SetSubtreesCommand implements CommandInterface
{
    /**
     * Constructs a SetSubtreesCommand object.
     *
     * @param array $subtrees
     *   The toolbar subtrees that will be set.
     */
    public function __construct(
        /**
         * The toolbar subtrees.
         */
        protected $subtrees
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function render(): array
    {
        return [
          'command' => 'setToolbarSubtrees',
          'subtrees' => array_map(strval(...), $this->subtrees),
        ];
    }

}
