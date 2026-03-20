<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Drupal\Component\Datetime\Time_Interface;
use Drupal\Core\Site\Settings;
/**
 * Defines the APCU backend factory.
 */
class Apcu_Backend_Factory implements Cache_Factory_Interface
{
    /**
     * The site prefix string.
     *
     * @var string
     */
    protected $site_prefix;
    /**
     * The APCU backend class to use.
     */
    protected string $backend_class;
    /**
     * Constructs an ApcuBackendFactory object.
     *
     * @param string $root
     *   The app root.
     * @param string $site_path
     *   The site path.
     * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksumProvider
     *   The cache tags checksum provider.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct($root, $site_path, protected \Drupal\Core\Cache\Cache_Tags_Checksum_Interface $checksum_provider, protected Time_Interface $time)
    {
        $this->site_prefix = Settings::get_apcu_prefix('apcu_backend', $root, $site_path);
        $this->backend_class = \Drupal\Core\Cache\Apcu_Backend::class;
    }
    /**
     * Gets ApcuBackend for the specified cache bin.
     *
     * @param string $bin
     *   The cache bin for which the object is created.
     *
     * @return \Drupal\Core\Cache\ApcuBackend
     *   The cache backend object for the specified cache bin.
     */
    public function get($bin)
    {
        return new $this->backend_class($bin, $this->site_prefix, $this->checksum_provider, $this->time);
    }
}