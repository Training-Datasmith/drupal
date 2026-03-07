<?php

declare(strict_types=1);

namespace Drupal\Tests\announcements_feed\Unit;

use Drupal\announcements_feed\AnnounceFetcher;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Simple test to ensure that asserts pass.
 */
#[Group('announcements_feed')]
class AnnounceFetcherUnitTest extends UnitTestCase
{
    /**
     * The Fetcher service object.
     *
     * @var \Drupal\announcements_feed\AnnounceFetcher
     */
    protected AnnounceFetcher $fetcher;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        $httpClient = $this->createMock('GuzzleHttp\ClientInterface');
        $config = $this->getConfigFactoryStub([
          'announcements_feed.settings' => [
            'max_age' => 86400,
            'cron_interval' => 21600,
            'limit' => 10,
          ],
        ]);
        $tempStore = $this->createMock('Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface');
        $tempStore->expects($this->once())
          ->method('get')
          ->willReturn($this->createMock('Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface'));

        $logger = $this->createMock('Psr\Log\LoggerInterface');
        $this->fetcher = new AnnounceFetcher($httpClient, $config, $tempStore, $logger, 'https://www.drupal.org/announcements.json');
    }

    /**
     * Test the ValidateUrl() method.
     *
     * @legacy-covers \Drupal\announcements_feed\AnnounceFetcher::validateUrl
     */
    #[DataProvider('urlProvider')]
    public function testValidateUrl($url, $isValid): void
    {
        $this->assertEquals($isValid, $this->fetcher->validateUrl($url));
    }

    /**
     * Data for the testValidateUrl.
     */
    public static function urlProvider(): array
    {
        return [
          ['https://www.drupal.org', true],
          ['https://drupal.org', true],
          ['https://api.drupal.org', true],
          ['https://a.drupal.org', true],
          ['https://123.drupal.org', true],
          ['https://api-new.drupal.org', true],
          ['https://api_new.drupal.org', true],
          ['https://api-.drupal.org', true],
          ['https://www.example.org', false],
          ['https://example.org', false],
          ['https://api.example.org/project/announce', false],
          ['https://-api.drupal.org', false],
          ['https://a.example.org/project/announce', false],
          ['https://test.drupaal.com', false],
          ['https://api.drupal.org.example.com', false],
          ['https://example.org/drupal.org', false],
        ];
    }

}
