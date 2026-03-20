<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Plugin\Validation\Constraint;

use Drupal\Core\Config\Config_Factory_Interface;
use Drupal\Core\Config\Schema\Type_Resolver;
use Drupal\Core\Dependency_Injection\Container_Injection_Interface;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraint_Validator;
/**
 * Validates that a given config object exists.
 */
class Config_Exists_Constraint_Validator extends Constraint_Validator implements Container_Injection_Interface
{
    /**
     * Constructs a ConfigExistsConstraintValidator object.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
     *   The config factory service.
     */
    public function __construct(
        /**
         * The config factory service.
         */
        protected Config_Factory_Interface $config_factory
    )
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container)
    {
        return new static($container->get('config.factory'));
    }
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $name, Constraint $constraint): void
    {
        assert($constraint instanceof Config_Exists_Constraint);
        // This constraint may be used to validate nullable (optional) values.
        if ($name === null) {
            return;
        }
        $constraint->prefix = Type_Resolver::resolve_dynamic_type_name($constraint->prefix, $this->context->get_object());
        if (!in_array($constraint->prefix . $name, $this->config_factory->list_all($constraint->prefix), true)) {
            $this->context->add_violation($constraint->message, ['@name' => $constraint->prefix . $name]);
        }
    }
}