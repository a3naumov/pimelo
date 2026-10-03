<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\ProxyController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ProxyController::class)]
final class ProxyControllerTest extends WebTestCase
{
    // ========================================================================
    // API responses and CORS
    // ========================================================================

    #[DataProvider('allowedOrigins')]
    public function testAllowsConfiguredFrontendOrigins(string $origin): void
    {
        $client = self::createClient();
        self::getContainer()->set('http_client', new MockHttpClient(new MockResponse('{"items":[]}', ['response_headers' => [
            'Content-Type: application/json',
        ]])));

        $client->request('GET', '/pim/web/products/', server: ['HTTP_ORIGIN' => $origin]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', $origin);
        self::assertResponseNotHasHeader('Access-Control-Allow-Credentials');
        self::assertSame(['items' => []], json_decode($client->getInternalResponse()->getContent(), true));
    }

    #[DataProvider('preflights')]
    public function testHandlesPreflightWithoutContactingAnyUpstream(string $path, string $method): void
    {
        $client = self::createClient();
        self::getContainer()->set('http_client', new MockHttpClient(static function (): never {
            self::fail('Preflight must not contact upstream.');
        }));

        $client->request('OPTIONS', $path, server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type, accept',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
        self::assertResponseHeaderSame('Access-Control-Max-Age', '3600');
        self::assertResponseNotHasHeader('Access-Control-Allow-Credentials');
        self::assertContains($method, explode(', ', $client->getResponse()->headers->get('Access-Control-Allow-Methods')));
    }

    #[DataProvider('invalidPreflights')]
    public function testRejectsUnsupportedPreflights(string $method, string $headers, int $status): void
    {
        $client = self::createClient();
        $client->request('OPTIONS', '/pim/web/products/', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => $headers,
        ]);

        self::assertResponseStatusCodeSame($status);
    }

    #[DataProvider('disallowedOrigins')]
    public function testDoesNotAllowUnlistedOrigins(string $origin): void
    {
        $client = self::createClient();
        self::getContainer()->set('http_client', new MockHttpClient(new MockResponse('[]')));

        $client->request('GET', '/pim/web/products/', server: ['HTTP_ORIGIN' => $origin]);

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Access-Control-Allow-Origin');
    }

    #[DataProvider('errorStatuses')]
    public function testUpstreamErrorsRemainReadableByFrontend(int $status): void
    {
        $client = self::createClient();
        self::getContainer()->set('http_client', new MockHttpClient(new MockResponse('{"error":"upstream"}', [
            'http_code' => $status,
            'response_headers' => ['Content-Type: application/json'],
        ])));

        $client->request('GET', '/pim/web/products/', server: ['HTTP_ORIGIN' => 'http://localhost:5173']);

        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
        self::assertSame(['error' => 'upstream'], json_decode($client->getInternalResponse()->getContent(), true));
    }

    #[DataProvider('gatewayFailures')]
    public function testGatewayErrorsRemainReadableByFrontend(TransportException $exception, int $status): void
    {
        $client = self::createClient();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Upstream request failed.', self::anything());
        self::getContainer()->set('logger', $logger);
        self::getContainer()->set('http_client', new MockHttpClient(static function () use ($exception): never {
            throw $exception;
        }));

        $client->request('GET', '/pim/web/products/', server: ['HTTP_ORIGIN' => 'http://localhost:5173']);

        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
        self::assertIsString(json_decode($client->getResponse()->getContent(), true)['error']);
    }

    // ========================================================================
    // Paths outside the service prefixes
    // ========================================================================

    #[DataProvider('unknownPaths')]
    public function testUnknownPrefixesReturnJson404WithoutCors(string $path): void
    {
        $client = self::createClient();
        $client->request('GET', $path, server: ['HTTP_ORIGIN' => 'http://localhost:5173']);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseNotHasHeader('Access-Control-Allow-Origin');
        self::assertSame(['error' => 'Unknown service.'], json_decode($client->getResponse()->getContent(), true));
    }

    public function testRequestWithoutOriginHasNoCorsHeaders(): void
    {
        $client = self::createClient();
        self::getContainer()->set('http_client', new MockHttpClient(new MockResponse('[]')));

        $client->request('GET', '/pim/web/products/');

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Access-Control-Allow-Origin');
    }
    // ========================================================================
    // Data providers
    // ========================================================================

    public static function allowedOrigins(): iterable
    {
        yield ['http://localhost:5173'];

        yield ['http://127.0.0.1:5173'];

        yield ['http://localhost:4173'];

        yield ['http://127.0.0.1:4173'];
    }

    public static function preflights(): iterable
    {
        foreach (['pim', 'search', 'media-storage', 'notifications'] as $service) {
            foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
                yield $service.' '.$method => ['/'.$service.'/resource', $method];
            }
        }
    }

    public static function invalidPreflights(): iterable
    {
        yield ['TRACE', 'content-type', 405];

        yield ['POST', 'x-not-allowed', 400];
    }

    public static function disallowedOrigins(): iterable
    {
        yield ['https://evil.test'];

        yield ['http://localhost:5173.evil.test'];

        yield ['http://localhost:3000'];
    }

    public static function errorStatuses(): iterable
    {
        yield [404];

        yield [422];

        yield [500];
    }

    public static function gatewayFailures(): iterable
    {
        yield [new TransportException('offline'), 502];

        yield [new TimeoutException('timeout'), 504];
    }

    public static function unknownPaths(): iterable
    {
        yield ['/web/products/'];

        yield ['/pim-other/'];

        yield ['/unknown/'];
    }
}
