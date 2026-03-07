<?php

declare(strict_types=1);

namespace Drupal\language\Plugin\LanguageNegotiation;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\language\Attribute\LanguageNegotiation;
use Drupal\language\LanguageNegotiationMethodBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class for identifying language from a selected language.
 */
#[LanguageNegotiation(
    id: LanguageNegotiationSelected::METHOD_ID,
    name: new TranslatableMarkup('Selected language'),
    weight: 12,
    description: new TranslatableMarkup('Language based on a selected language.'),
    config_route_name: 'language.negotiation_selected'
)]
class LanguageNegotiationSelected extends LanguageNegotiationMethodBase
{
    /**
     * The language negotiation method id.
     */
    public const METHOD_ID = 'language-selected';

    /**
     * {@inheritdoc}
     */
    public function getLangcode(?Request $request = null)
    {
        if ($this->languageManager) {
            return $this->config->get('language.negotiation')->get('selected_langcode');
        }

        return null;
    }

}
