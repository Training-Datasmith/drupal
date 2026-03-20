<?php

declare (strict_types=1);
namespace Drupal\Core\Datetime;

use Drupal\Core\Cache\Cacheable_Dependency_Interface;
use Drupal\Core\Cache\Unchanging_Cacheable_Dependency_Trait;
use Drupal\Core\Render\Renderable_Interface;
/**
 * Contains a formatted time difference.
 */
class Formatted_Date_Diff implements Renderable_Interface, Cacheable_Dependency_Interface
{
    use Unchanging_Cacheable_Dependency_Trait;
    /**
     * Creates a new FormattedDateDiff instance.
     *
     * @param string $string
     *   The formatted time difference.
     * @param int $maxAge
     *   The maximum time in seconds that this string may be cached.
     */
    public function __construct(
        /**
         * The actual formatted time difference.
         */
        protected $string,
        /**
         * The maximum time in seconds that this string may be cached.
         *
         * Let's say the time difference is 1 day 1 hour. In this case, we can cache
         * it until now + 1 hour, so maxAge is 3600 seconds.
         */
        protected $max_age
    )
    {
    }
    /**
     * @return string
     *   The actual formatted time difference.
     */
    public function get_string()
    {
        return $this->string;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_max_age()
    {
        return $this->max_age;
    }
    /**
     * {@inheritdoc}
     */
    public function to_renderable(): array
    {
        return ['#markup' => $this->string, '#cache' => ['max-age' => $this->max_age]];
    }
}