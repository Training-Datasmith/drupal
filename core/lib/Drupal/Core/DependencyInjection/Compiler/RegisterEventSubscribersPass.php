<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Event_Dispatcher\Dependency_Injection\Register_Listeners_Pass;
/**
 * Wraps the Symfony event subscriber pass to use different tag names.
 */
class Register_Event_Subscribers_Pass implements Compiler_Pass_Interface
{
    /**
     * Constructs a RegisterEventSubscribersPass object.
     *
     * @param \Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass $pass
     *   The Symfony compiler pass that registers event subscribers.
     */
    public function __construct(protected Register_Listeners_Pass $pass)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        $this->rename_tag($container, 'event_subscriber', 'kernel.event_subscriber');
        $this->pass->process($container);
        $this->rename_tag($container, 'kernel.event_subscriber', 'event_subscriber');
    }
    /**
     * Renames tags in the container.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     *   The container.
     * @param string $source_tag
     *   The tag to be renamed.
     * @param string $target_tag
     *   The tag to rename with.
     */
    protected function rename_tag(Container_Builder $container, string $source_tag, string $target_tag): void
    {
        foreach ($container->get_definitions() as $definition) {
            if ($definition->has_tag($source_tag)) {
                $attributes = $definition->get_tag($source_tag)[0];
                $definition->add_tag($target_tag, $attributes);
                $definition->clear_tag($source_tag);
            }
        }
    }
}