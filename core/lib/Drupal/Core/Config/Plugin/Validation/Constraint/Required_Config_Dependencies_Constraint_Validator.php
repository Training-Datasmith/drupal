<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Plugin\Validation\Constraint;

use Drupal\Core\Config\Entity\Config_Entity_Interface;
use Drupal\Core\Config\Entity\Config_Entity_Type_Interface;
use Drupal\Core\Dependency_Injection\Container_Injection_Interface;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraint_Validator;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\Unexpected_Type_Exception;
/**
 * Validates the RequiredConfigDependencies constraint.
 */
class Required_Config_Dependencies_Constraint_Validator extends Constraint_Validator implements Container_Injection_Interface
{
    /**
     * Constructs a RequiredConfigDependenciesConstraintValidator object.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager service.
     */
    public function __construct(
        /**
         * The entity type manager service.
         */
        protected Entity_Type_Manager_Interface $entity_type_manager
    )
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container)
    {
        return new static($container->get('entity_type.manager'));
    }
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $entity, Constraint $constraint): void
    {
        assert($constraint instanceof Required_Config_Dependencies_Constraint);
        // Only config entities can have config dependencies.
        if (!$entity instanceof Config_Entity_Interface) {
            throw new Unexpected_Type_Exception($entity, Config_Entity_Interface::class);
        }
        $config_dependencies = $entity->get_dependencies()['config'] ?? [];
        foreach ($constraint->entity_types as $entity_type_id) {
            $entity_type = $this->entity_type_manager->get_definition($entity_type_id);
            if (!$entity_type instanceof Config_Entity_Type_Interface) {
                throw new LogicException("'{$entity_type_id}' is not a config entity type.");
            }
            // Ensure the current entity type's config prefix is found in the config
            // dependencies of the entity being validated.
            $pattern = sprintf('/^%s\.\w+/', $entity_type->get_config_prefix());
            if (!preg_grep($pattern, $config_dependencies)) {
                $this->context->add_violation($constraint->message, ['@entity_type' => $entity->get_entity_type()->get_singular_label(), '@dependency_type' => $entity_type->get_singular_label()]);
            }
        }
    }
}