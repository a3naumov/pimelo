<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http;

use App\Http\ProxyHeaders;
use App\Http\ProxyRequestBody;
use App\Http\ServiceProxy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[CoversClass(ServiceProxy::class)]
final class ServiceProxyTest extends TestCase
{
    // ========================================================================
    // Routing and request forwarding
    // ========================================================================

    public function testPreservesMethodRawQueryAndBodyAndFiltersUntrustedHeaders(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://pim:8080/web/products/?tag=a&tag=b&value=a%20b', $url);
            self::assertSame('{"sku":"test"}', $options['body']);
            self::assertSame(0, $options['max_redirects']);
            self::assertFalse($options['buffer']);
            self::assertSame(['x-forwarded-prefix: /pim'], $options['normalized_headers']['x-forwarded-prefix']);
            self::assertSame(['x-forwarded-host: localhost'], $options['normalized_headers']['x-forwarded-host']);
            self::assertArrayNotHasKey('x-secret', $options['normalized_headers']);
            self::assertArrayNotHasKey('forwarded', $options['normalized_headers']);

            return new MockResponse('{"id":"123"}', ['http_code' => 201, 'response_headers' => [
                'Content-Type: application/json',
                'Location: http://pim:8080/web/products/123',
                'Connection: x-private',
                'X-Private: hidden',
                'Access-Control-Allow-Origin: *',
            ]]);
        });
        $request = Request::create('/pim/web/products/?tag=a&tag=b&value=a%20b', 'POST', server: [
            'REMOTE_ADDR' => '8.8.8.8',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CONNECTION' => 'x-secret',
            'HTTP_X_SECRET' => 'private',
            'HTTP_FORWARDED' => 'host=evil.test',
            'HTTP_X_FORWARDED_HOST' => 'evil.test',
            'HTTP_X_FORWARDED_PREFIX' => '/evil',
        ], content: '{"sku":"test"}');

        $response = $this->proxy($client)->forward($request);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('http://localhost/pim/web/products/123', $response->headers->get('Location'));
        self::assertFalse($response->headers->has('X-Private'));
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));
        self::assertSame('{"id":"123"}', $this->consume($response));
    }

    #[DataProvider('serviceRoutes')]
    public function testRoutesOnlyConfiguredServicePrefixes(string $path, string $url): void
    {
        $client = new MockHttpClient(function (string $method, string $actualUrl) use ($url): MockResponse {
            self::assertSame($url, $actualUrl);

            return new MockResponse('ok');
        });

        self::assertSame('ok', $this->consume($this->proxy($client)->forward(Request::create($path))));
    }

    public function testUnknownServiceDoesNotCallUpstream(): void
    {
        $client = new MockHttpClient(static function (): never {
            self::fail('Unknown services must not issue HTTP requests.');
        });

        $response = $this->proxy($client)->forward(Request::create('/web/products/'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('{"error":"Unknown service."}', $response->getContent());
    }

    // ========================================================================
    // Responses, downloads and failures
    // ========================================================================

    #[DataProvider('upstreamStatuses')]
    public function testPreservesUpstreamStatusAndHeaders(int $status): void
    {
        $client = new MockHttpClient(new MockResponse('upstream', ['http_code' => $status, 'response_headers' => [
            'Content-Type: application/octet-stream',
            'Content-Disposition: attachment; filename="report.bin"',
            'Allow: GET, HEAD',
        ]]));

        $response = $this->proxy($client)->forward(Request::create('/media-storage/download'));

        self::assertSame($status, $response->getStatusCode());
        self::assertSame('GET, HEAD', $response->headers->get('Allow'));
        self::assertSame('attachment; filename="report.bin"', $response->headers->get('Content-Disposition'));
        self::assertSame('upstream', $this->consume($response));
    }

    public function testHeadHasNoBody(): void
    {
        $response = $this->proxy(new MockHttpClient(new MockResponse('ignored')))->forward(Request::create('/pim/', 'HEAD'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    public function testStreamsBinaryBytesUnchanged(): void
    {
        $body = static function (): iterable {
            yield "\x00\xff";

            yield "\x80\x01";
        };
        $response = $this->proxy(new MockHttpClient(new MockResponse($body())))->forward(Request::create('/media-storage/download'));

        self::assertSame("\x00\xff\x80\x01", $this->consume($response));
    }

    public function testStreamFailureDoesNotAppendJsonToPartialBody(): void
    {
        $body = static function (): iterable {
            yield 'partial';

            throw new TransportException('Connection lost.');
        };
        $response = $this->proxy(new MockHttpClient(new MockResponse($body())))->forward(Request::create('/media-storage/download'));

        self::assertSame('partial', $this->consume($response));
    }

    #[DataProvider('transportFailures')]
    public function testMapsTransportFailuresToSafeJsonErrors(TransportException $exception, int $status, string $message): void
    {
        $client = new MockHttpClient(static function () use ($exception): never {
            throw $exception;
        });
        $response = $this->proxy($client)->forward(Request::create('/pim/web/products/'));

        self::assertSame($status, $response->getStatusCode());
        self::assertSame(['error' => $message], json_decode($response->getContent(), true));
    }

    private function proxy(MockHttpClient $client): ServiceProxy
    {
        return new ServiceProxy($client, new ProxyHeaders(), new ProxyRequestBody(), new NullLogger(), [
            'pim' => 'http://pim:8080',
            'search' => 'http://search:8080',
            'media-storage' => 'http://media-storage:8080',
            'notifications' => 'http://notifications:8080',
        ], 10, 60);
    }

    private function consume(StreamedResponse $response): string
    {
        ob_start();

        try {
            $response->sendContent();

            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
    // ========================================================================
    // Data providers
    // ========================================================================

    public static function serviceRoutes(): iterable
    {
        yield ['/pim', 'http://pim:8080/'];

        yield ['/pim/', 'http://pim:8080/'];

        yield ['/search/items?q=x', 'http://search:8080/items?q=x'];

        yield ['/media-storage/files/a%20b', 'http://media-storage:8080/files/a%20b'];

        yield ['/notifications/', 'http://notifications:8080/'];
    }

    public static function upstreamStatuses(): iterable
    {
        foreach ([200, 301, 404, 405, 422, 500] as $status) {
            yield (string) $status => [$status];
        }
    }

    public static function transportFailures(): iterable
    {
        yield 'connection' => [new TransportException('Internal host details'), 502, 'Upstream unavailable.'];

        yield 'timeout' => [new TimeoutException('Internal host details'), 504, 'Upstream timed out.'];
    }
}
