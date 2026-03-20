<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Action\Plugin\Config_Action\Deriver;

// cspell:ignore inflector
use Drupal\Component\Plugin\Derivative\Deriver_Base;
use Drupal\Component\Plugin\Plugin_Base;
use Drupal\Core\Config\Action\Attribute\Action_Method;
use Drupal\Core\Config\Action\Entity_Method_Exception;
use Drupal\Core\Config\Entity\Config_Entity_Type_Interface;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Drupal\Core\Plugin\Discovery\Container_Deriver_Interface;
use Drupal\Core\String_Translation\String_Translation_Trait;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\String\Inflector\English_Inflector;
use Symfony\Component\String\Inflector\Inflector_Interface;
/**
 * Derives config action methods from attributed config entity methods.
 *
 * @internal
 *   This API is experimental.
 */
final class Entity_Method_Deriver extends Deriver_Base implements Container_Deriver_Interface
{
    use String_Translation_Trait;
    /**
     * Inflector to pluralize words.
     */
    protected readonly Inflector_Interface $inflector;
    /**
     * Constructs new EntityMethodDeriver.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager.
     */
    public function __construct(protected readonly Entity_Type_Manager_Interface $entity_type_manager)
    {
        $this->inflector = new English_Inflector();
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container, $base_plugin_id): static
    {
        return new static($container->get('entity_type.manager'));
    }
    /**
     * {@inheritdoc}
     */
    public function get_derivative_definitions($base_plugin_definition)
    {
        // Scan all the config entity classes for attributes.
        foreach ($this->entity_type_manager->get_definitions() as $entity_type) {
            if ($entity_type instanceof Config_Entity_Type_Interface) {
                $reflection_class = new \ReflectionClass($entity_type->get_class());
                while ($reflection_class) {
                    foreach ($reflection_class->get_methods(\ReflectionMethod::IS_PUBLIC) as $method) {
                        // Only process a method if it is declared on the current class.
                        // Methods on the parent class will be processed later. This allows
                        // for a parent to have an attribute and an overriding class does
                        // not need one. For example,
                        // \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay::setComponent()
                        // and \Drupal\Core\Entity\EntityDisplayBase::setComponent().
                        if ($method->get_declaring_class()->get_name() === $reflection_class->get_name()) {
                            foreach ($method->get_attributes(Action_Method::class) as $attribute) {
                                $this->process_method($method, $attribute->new_instance(), $entity_type, $base_plugin_definition);
                            }
                        }
                    }
                    $reflection_class = $reflection_class->get_parent_class();
                }
            }
        }
        return $this->derivatives;
    }
    /**
     * Processes a method to create derivatives.
     *
     * @param \ReflectionMethod $method
     *   The entity method.
     * @param \Drupal\Core\Config\Action\Attribute\ActionMethod $action_attribute
     *   The entity method attribute.
     * @param \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type
     *   The entity type.
     * @param array $derivative
     *   The base plugin definition that will used to create the derivative.
     */
    private function process_method(\ReflectionMethod $method, Action_Method $action_attribute, Config_Entity_Type_Interface $entity_type, array $derivative): void
    {
        $derivative['admin_label'] = $action_attribute->admin_label ?: $this->t('@entity_type @method', ['@entity_type' => $entity_type->get_label(), '@method' => $method->name]);
        $derivative['constructor_args'] = ['method' => $method->name, 'exists' => $action_attribute->exists, 'numberOfParams' => $method->get_number_of_parameters(), 'numberOfRequiredParams' => $method->get_number_of_required_parameters(), 'pluralized' => false];
        $derivative['entity_types'] = [$entity_type->id()];
        $action_name = $action_attribute->name ?: $method->name;
        // Build a config action identifier from the entity type's config
        // prefix  and the method name. For example, the Role entity adds a
        // 'user.role:grantPermission' action.
        $this->add_derivative($action_name, $entity_type, $derivative, $method->name);
        $pluralized_name = match (true) {
            is_string($action_attribute->pluralize) => $action_attribute->pluralize,
            $action_attribute->pluralize === false => '',
            default => $this->inflector->pluralize($action_name)[0],
        };
        // Add a pluralized version of the plugin.
        if (strlen($pluralized_name) > 0) {
            $derivative['constructor_args']['pluralized'] = true;
            $derivative['admin_label'] = $this->t('@admin_label (multiple calls)', ['@admin_label' => $derivative['admin_label']]);
            $this->add_derivative($pluralized_name, $entity_type, $derivative, $method->name);
        }
    }
    /**
     * Adds a derivative.
     *
     * @param string $action_id
     *   The action ID.
     * @param \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type
     *   The entity type.
     * @param array $derivative
     *   The derivative definition.
     * @param string $methodName
     *   The method name.
     */
    private function add_derivative(string $action_id, Config_Entity_Type_Interface $entity_type, array $derivative, string $method_name): void
    {
        $id = $entity_type->get_config_prefix() . Plugin_Base::DERIVATIVE_SEPARATOR . $action_id;
        if (isset($this->derivatives[$id])) {
            throw new Entity_Method_Exception(sprintf('Duplicate action can not be created for ID \'%s\' for %s::%s(). The existing action is for the ::%s() method', $id, $entity_type->get_class(), $method_name, $this->derivatives[$id]['constructor_args']['method']));
        }
        $this->derivatives[$id] = $derivative;
    }
}