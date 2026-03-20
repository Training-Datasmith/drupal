<?php

declare(strict_types=1);

namespace Drupal\language;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Converts parameters for upcasting entity IDs to full objects.
 */
class LanguageConverter implements ParamConverterInterface
{
    /**
     * Constructs a new LanguageConverter.
     *
     * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
     *   The language manager.
     */
    public function __construct(protected \Drupal\Core\Language\LanguageManagerInterface $languageManager)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function convert($value, $definition, $name, array $defaults)
    {
        if (!empty($value)) {
            return $this->languageManager->getLanguage($value);
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function applies($definition, $name, Route $route): bool
    {
        return (!empty($definition['type']) && $definition['type'] == 'language');
    }

}
