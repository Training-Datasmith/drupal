<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\StackMiddleware;

use Drupal\Core\StackMiddleware\ContentLength;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests Drupal\Core\StackMiddleware\ContentLength.
 */
#[CoversClass(ContentLength::class)]
#[Group('Middleware')]
class ContentLengthTest extends UnitTestCase
{
    /**
     * Tests handle.
     */
    #[DataProvider('providerTestSetContentLengthHeader')]
    public function testHandle(false|int $expected_header, Response $response): void
    {
        $httpKernel = $this->prophesize(HttpKernelInterface::class);
        $request = Request::create('/');
        $httpKernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true)->willReturn($response);
        $middleware = new ContentLength($httpKernel->reveal());
        $response = $middleware->handle($request);
        if ($expected_header === false) {
            $this->assertFalse($response->headers->has('Content-Length'));
            return;
        }
        $this->assertSame((string) $expected_header, $response->headers->get('Content-Length'));
    }

    public static function providerTestSetContentLengthHeader(): array
    {
        return [
          'Informational' => [
            false,
            new Response('', 101),
          ],
          '200 ok' => [
            12,
            new Response('Test content', 200),
          ],
          '204' => [
            false,
            new Response('Test content', 204),
          ],
          '304' => [
            false,
            new Response('Test content', 304),
          ],
          'Client error' => [
            13,
            new Response('Access denied', 403),
          ],
          'Server error' => [
            false,
            new Response('Test content', 500),
          ],
          '200 with transfer-encoding' => [
            false,
            new Response('Test content', 200, ['Transfer-Encoding' => 'Chunked']),
          ],
          '200 with FalseContentResponse' => [
            false,
            new FalseContentResponse('Test content', 200),
          ],
          '200 with StreamedResponse' => [
            false,
            new StreamedResponse(status: 200),
          ],

        ];
    }

}

/**
 * Response that returns FALSE from ::getContent().
 */
class FalseContentResponse extends Response
{
    /**
     * {@inheritdoc}
     */
    public function getContent(): string|false
    {
        return false;
    }

}
