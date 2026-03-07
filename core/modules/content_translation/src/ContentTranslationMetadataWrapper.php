<?php

declare(strict_types=1);

namespace Drupal\content_translation;

use Drupal\user\UserInterface;

/**
 * Base class for content translation metadata wrappers.
 */
class ContentTranslationMetadataWrapper implements ContentTranslationMetadataWrapperInterface
{
    /**
     * Initializes an instance of the content translation metadata handler.
     *
     * @param \Drupal\Core\Entity\EntityInterface $translation
     *   The entity translation to be wrapped.
     * @param ContentTranslationHandlerInterface $handler
     *   The content translation handler.
     */
    public function __construct(
        /**
         * The wrapped entity translation.
         */
        protected \Drupal\Core\Entity\EntityInterface $translation,
        protected \Drupal\content_translation\ContentTranslationHandlerInterface $handler
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getSource()
    {
        return $this->translation->get('content_translation_source')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setSource($source): static
    {
        $this->translation->set('content_translation_source', $source);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isOutdated(): bool
    {
        return (bool) $this->translation->get('content_translation_outdated')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setOutdated($outdated): static
    {
        $this->translation->set('content_translation_outdated', $outdated);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthor()
    {
        return $this->translation->hasField('content_translation_uid') ? $this->translation->get('content_translation_uid')->entity : $this->translation->getOwner();
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthor(UserInterface $account): static
    {
        $field_name = $this->translation->hasField('content_translation_uid') ? 'content_translation_uid' : 'uid';
        $this->setFieldOnlyIfTranslatable($field_name, $account->id());
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isPublished(): bool
    {
        $field_name = $this->translation->hasField('content_translation_status') ? 'content_translation_status' : 'status';
        return (bool) $this->translation->get($field_name)->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setPublished($published): static
    {
        $field_name = $this->translation->hasField('content_translation_status') ? 'content_translation_status' : 'status';
        $this->setFieldOnlyIfTranslatable($field_name, $published);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedTime()
    {
        $field_name = $this->translation->hasField('content_translation_created') ? 'content_translation_created' : 'created';
        return $this->translation->get($field_name)->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedTime($timestamp): static
    {
        $field_name = $this->translation->hasField('content_translation_created') ? 'content_translation_created' : 'created';
        $this->setFieldOnlyIfTranslatable($field_name, $timestamp);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getChangedTime()
    {
        return $this->translation->hasField('content_translation_changed') ? $this->translation->get('content_translation_changed')->value : $this->translation->getChangedTime();
    }

    /**
     * {@inheritdoc}
     */
    public function setChangedTime($timestamp): static
    {
        $field_name = $this->translation->hasField('content_translation_changed') ? 'content_translation_changed' : 'changed';
        $this->setFieldOnlyIfTranslatable($field_name, $timestamp);
        return $this;
    }

    /**
     * Updates a field value, only if the field is translatable.
     *
     * @param string $field_name
     *   The name of the field.
     * @param mixed $value
     *   The field value to be set.
     */
    protected function setFieldOnlyIfTranslatable($field_name, $value)
    {
        if ($this->translation->getFieldDefinition($field_name)->isTranslatable()) {
            $this->translation->set($field_name, $value);
        }
    }

}
