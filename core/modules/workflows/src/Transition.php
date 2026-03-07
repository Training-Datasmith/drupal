<?php

declare(strict_types=1);

namespace Drupal\workflows;

/**
 * A transition value object that describes the transition between states.
 */
class Transition implements TransitionInterface
{
    /**
     * Transition constructor.
     *
     * @param \Drupal\workflows\WorkflowTypeInterface $workflow
     *   The workflow the state is attached to.
     * @param string $id
     *   The transition's ID.
     * @param string $label
     *   The transition's label.
     * @param array $fromStateIds
     *   A list of from state IDs.
     * @param string $toStateId
     *   The to state ID.
     * @param int $weight
     *   (optional) The transition's weight. Defaults to 0.
     */
    public function __construct(
        protected \Drupal\workflows\WorkflowTypeInterface $workflow,
        /**
         * The transition's ID.
         */
        protected $id,
        /**
         * The transition's label.
         */
        protected $label,
        /**
         * The transition's from state IDs.
         */
        protected array $fromStateIds,
        /**
         * The transition's to state ID.
         */
        protected $toStateId,
        /**
         * The transition's weight.
         */
        protected $weight = 0
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function label()
    {
        return $this->label;
    }

    /**
     * {@inheritdoc}
     */
    public function from()
    {
        return $this->workflow->getStates($this->fromStateIds);
    }

    /**
     * {@inheritdoc}
     */
    public function to()
    {
        return $this->workflow->getState($this->toStateId);
    }

    /**
     * {@inheritdoc}
     */
    public function weight()
    {
        return $this->weight;
    }

}
