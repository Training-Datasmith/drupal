<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Attribute;

/**
 * Provides a base class for classed attributes.
 */
abstract class Attribute_Base implements Attribute_Interface
{
    /**
     * The class used for this attribute class.
     *
     * @var class-string
     */
    protected string $class;
    /**
     * The provider of the attribute class.
     */
    protected string|null $provider = null;
    /**
     * The dependencies for the attribute class.
     *
     * Dependencies are keyed by type. If the type is 'class', 'interface', or
     * 'trait', the values for the type are class names. If the type is
     * 'provider', the values for the type are provider names.
     *
     * @var array{"class"?: list<class-string>, "interface"?: list<class-string>, "trait"?: list<class-string>, "provider"?: list<string>}|null
     */
    protected array|null $dependencies = null;
    /**
     * @param string $id
     *   The attribute class ID.
     */
    public function __construct(protected readonly string $id)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get_provider(): ?string
    {
        return $this->provider;
    }
    /**
     * {@inheritdoc}
     */
    public function set_provider(string $provider): void
    {
        $this->provider = $provider;
    }
    /**
     * {@inheritdoc}
     */
    public function get_id(): string
    {
        return $this->id;
    }
    /**
     * {@inheritdoc}
     */
    public function get_class(): string
    {
        return $this->class;
    }
    /**
     * {@inheritdoc}
     */
    public function set_class(string $class): void
    {
        $this->class = $class;
    }
    /**
     * {@inheritdoc}
     */
    public function get_dependencies(): ?array
    {
        return $this->dependencies;
    }
    /**
     * {@inheritdoc}
     */
    public function set_dependencies(?array $dependencies): void
    {
        $this->dependencies = $dependencies;
    }
    /**
     * {@inheritdoc}
     */
    public function get(): array|object
    {
        return array_filter(get_object_vars($this) + ['class' => $this->get_class(), 'provider' => $this->get_provider()], fn($value, $key) => !($value === null && in_array($key, ['deriver', 'provider', 'dependencies'])), ARRAY_FILTER_USE_BOTH);
    }
}