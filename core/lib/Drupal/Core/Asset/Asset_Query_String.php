<?php

declare (strict_types=1);
namespace Drupal\Core\Asset;

use Drupal\Component\Datetime\Time_Interface;
use Drupal\Core\State\State_Interface;
/**
 * Stores a cache busting query string service for asset URLs.
 *
 * The string changes on every update or full cache flush, forcing browsers to
 * load a new copy of the files, as the URL changed.
 */
class Asset_Query_String implements Asset_Query_String_Interface
{
    /**
     * The key used for state.
     */
    public const STATE_KEY = 'asset.css_js_query_string';
    /**
     * Creates a new AssetQueryString instance.
     *
     * @param \Drupal\Core\State\StateInterface $state
     *   State service.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   System time service.
     */
    public function __construct(protected State_Interface $state, protected Time_Interface $time)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        // The timestamp is converted to base 36 in order to make it more compact.
        $this->state->set(self::STATE_KEY, base_convert(strval($this->time->get_request_time()), 10, 36));
    }
    /**
     * {@inheritdoc}
     */
    public function get(): string
    {
        return $this->state->get(self::STATE_KEY, '0');
    }
}