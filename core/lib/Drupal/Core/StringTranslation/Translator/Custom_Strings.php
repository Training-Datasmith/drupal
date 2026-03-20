<?php

declare(strict_types=1);

namespace Drupal\Core\StringTranslation\Translator;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Site\Settings;

/**
 * String translator using overrides from variables.
 *
 * This is a high performance way to provide a handful of string replacements.
 * See settings.php for examples.
 */
class CustomStrings extends StaticTranslation
{
    use DependencySerializationTrait;

    /**
     * Constructs a CustomStrings object.
     *
     * @param \Drupal\Core\Site\Settings $settings
     *   The settings read only object.
     */
    public function __construct(protected \Drupal\Core\Site\Settings $settings)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function getLanguage($langcode)
    {
        return $this->settings->get('locale_custom_strings_' . $langcode, []);
    }

}
