<?php

declare(strict_types=1);

namespace Drupal\taxonomy\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;

/**
 * Argument handler for to modify depth for a previous term.
 *
 * This handler is actually part of the node table and has some restrictions,
 * because it uses a subquery to find nodes with.
 *
 * @ingroup views_argument_handlers
 */
#[ViewsArgument(
    id: 'taxonomy_index_tid_depth_modifier',
)]
class IndexTidDepthModifier extends ArgumentPluginBase
{
    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(&$form, FormStateInterface $form_state)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function query($group_by = false)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function preQuery(): void
    {
        // We don't know our argument yet, but it's based upon our position:
        $argument = $this->view->args[$this->position] ?? null;
        if (!is_numeric($argument)) {
            return;
        }

        if ($argument > 10) {
            $argument = 10;
        }

        if ($argument < -10) {
            $argument = -10;
        }

        // Figure out which argument preceded us.
        $keys = array_reverse(array_keys($this->view->argument));
        $skip = true;
        foreach ($keys as $key) {
            if ($key == $this->options['id']) {
                $skip = false;
                continue;
            }

            if ($skip) {
                continue;
            }

            if (empty($this->view->argument[$key])) {
                continue;
            }

            $handler = &$this->view->argument[$key];
            if (empty($handler->definition['accept depth modifier'])) {
                continue;
            }

            // Finally!
            $handler->options['depth'] = $argument;
        }
    }

}
