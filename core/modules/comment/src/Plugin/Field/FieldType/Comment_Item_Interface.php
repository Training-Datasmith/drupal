<?php

declare(strict_types=1);

namespace Drupal\comment\Plugin\Field\FieldType;

/**
 * Interface definition for Comment items.
 */
interface CommentItemInterface
{
    /**
     * Comments for this entity are hidden.
     */
    public const HIDDEN = 0;

    /**
     * Comments for this entity are closed.
     */
    public const CLOSED = 1;

    /**
     * Comments for this entity are open.
     */
    public const OPEN = 2;

    /**
     * Comment form should be displayed on a separate page.
     */
    public const FORM_SEPARATE_PAGE = 0;

    /**
     * Comment form should be shown below post or list of comments.
     */
    public const FORM_BELOW = 1;

}
