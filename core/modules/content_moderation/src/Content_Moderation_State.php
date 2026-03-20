<?php

declare(strict_types=1);

namespace Drupal\content_moderation;

use Drupal\workflows\StateInterface;

/**
 * A value object representing a workflow state for content moderation.
 */
class ContentModerationState implements StateInterface
{
    /**
     * ContentModerationState constructor.
     *
     * Decorates state objects to add methods to determine if an entity should be
     * published or made the default revision.
     *
     * @param \Drupal\workflows\StateInterface $state
     *   The vanilla state object from the Workflow module.
     * @param bool $published
     *   (optional) TRUE if entities should be published if in this state, FALSE
     *   if not. Defaults to FALSE.
     * @param bool $defaultRevision
     *   (optional) TRUE if entities should be the default revision if in this
     *   state, FALSE if not. Defaults to FALSE.
     */
    public function __construct(
        protected \Drupal\workflows\StateInterface $state,
        /**
         * If entities should be published if in this state.
         */
        protected $published = false,
        /**
         * If entities should be the default revision if in this state.
         */
        protected $defaultRevision = false
    ) {
    }

    /**
     * Determines if entities should be published if in this state.
     *
     * @return bool
     *   TRUE if entities should be published if in this state, FALSE if not.
     */
    public function isPublishedState()
    {
        return $this->published;
    }

    /**
     * Determines if entities should be the default revision if in this state.
     *
     * @return bool
     *   TRUE if entities should be the default revision if in this state, FALSE
     *   if not.
     */
    public function isDefaultRevisionState()
    {
        return $this->defaultRevision;
    }

    /**
     * {@inheritdoc}
     */
    public function id()
    {
        return $this->state->id();
    }

    /**
     * {@inheritdoc}
     */
    public function label()
    {
        return $this->state->label();
    }

    /**
     * {@inheritdoc}
     */
    public function weight()
    {
        return $this->state->weight();
    }

    /**
     * {@inheritdoc}
     */
    public function canTransitionTo($to_state_id)
    {
        return $this->state->canTransitionTo($to_state_id);
    }

    /**
     * {@inheritdoc}
     */
    public function getTransitionTo($to_state_id)
    {
        return $this->state->getTransitionTo($to_state_id);
    }

    /**
     * {@inheritdoc}
     */
    public function getTransitions()
    {
        return $this->state->getTransitions();
    }

}
