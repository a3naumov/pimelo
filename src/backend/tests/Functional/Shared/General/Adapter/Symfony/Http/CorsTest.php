<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\General\Adapter\Symfony\Http;

use App\Core\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class CorsTest extends WebTestCase
{
    #[DataProvider('allowedOrigins')]
    public function testAllowsFrontendOriginsOnApiResponses(string $origin): void
    {
        $client = self::createClient();
        $client->request('GET', '/web/products/', server: ['HTTP_ORIGIN' => $origin]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', $origin);
        self::assertResponseNotHasHeader('Access-Control-Allow-Credentials');
    }

    #[DataProvider('productPreflights')]
    public function testAllowsProductPreflights(string $method, string $path): void
    {
        $client = self::createClient();
        $client->request('OPTIONS', $path, server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type, accept',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
        self::assertResponseHeaderSame('Access-Control-Max-Age', '3600');
        self::assertResponseNotHasHeader('Access-Control-Allow-Credentials');
        self::assertContains($method, explode(', ', $client->getResponse()->headers->get('Access-Control-Allow-Methods', '')));
        self::assertContains('content-type', explode(', ', strtolower($client->getResponse()->headers->get('Access-Control-Allow-Headers', ''))));
    }

    #[DataProvider('disallowedOrigins')]
    public function testDoesNotAllowUnlistedOrigins(string $origin): void
    {
        $client = self::createClient();
        $client->request('GET', '/web/products/', server: ['HTTP_ORIGIN' => $origin]);

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Access-Control-Allow-Origin');

        $client->request('OPTIONS', '/web/products/', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        self::assertResponseNotHasHeader('Access-Control-Allow-Origin');
    }

    #[DataProvider('invalidPreflights')]
    public function testRejectsUnsupportedPreflightRequests(string $method, string $headers, int $status): void
    {
        $client = self::createClient();
        $client->request('OPTIONS', '/web/products/', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => $headers,
        ]);

        self::assertResponseStatusCodeSame($status);
    }

    #[DataProvider('apiErrors')]
    public function testKeepsApiErrorsReadableByTheFrontend(string $method, string $path, int $status, ?string $content): void
    {
        $client = self::createClient();
        $client->request($method, $path, server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: $content);

        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function testKeepsServerErrorsReadableByTheFrontend(): void
    {
        $client = self::createClient();
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects($this->once())->method('findAll')->willThrowException(new \RuntimeException('Database unavailable'));
        self::getContainer()->set(ProductRepositoryInterface::class, $repository);

        $client->request('GET', '/web/products/', server: ['HTTP_ORIGIN' => 'http://localhost:5173']);

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function testRequestsWithoutOriginKeepTheirExistingBehavior(): void
    {
        $client = self::createClient();
        $client->request('GET', '/web/products/');

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Access-Control-Allow-Origin');

        $client->request('OPTIONS', '/web/products/');

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
        self::assertResponseNotHasHeader('Access-Control-Allow-Origin');
    }

    #[DataProvider('nonApiPaths')]
    public function testDoesNotApplyCorsOutsideTheWebApi(string $path, int $status): void
    {
        $client = self::createClient();
        $client->request('GET', $path, server: ['HTTP_ORIGIN' => 'http://localhost:5173']);

        self::assertResponseStatusCodeSame($status);
        self::assertResponseNotHasHeader('Access-Control-Allow-Origin');
    }

    public static function allowedOrigins(): iterable
    {
        yield 'Vite localhost' => ['http://localhost:5173'];

        yield 'Vite loopback' => ['http://127.0.0.1:5173'];

        yield 'preview localhost' => ['http://localhost:4173'];

        yield 'preview loopback' => ['http://127.0.0.1:4173'];
    }

    public static function productPreflights(): iterable
    {
        $productPath = '/web/products/01994731-0123-7000-8000-000000000000';

        yield 'create' => ['POST', '/web/products/'];

        yield 'edit' => ['PATCH', $productPath];

        yield 'delete' => ['DELETE', $productPath];

        yield 'restore' => ['POST', $productPath.'/restore'];

        yield 'permanent delete' => ['DELETE', $productPath.'/permanent'];
    }

    public static function disallowedOrigins(): iterable
    {
        yield 'external domain' => ['https://untrusted.example'];

        yield 'unlisted port' => ['http://localhost:5174'];

        yield 'different scheme' => ['https://localhost:5173'];
    }

    public static function invalidPreflights(): iterable
    {
        yield 'unlisted method' => ['TRACE', 'content-type', Response::HTTP_METHOD_NOT_ALLOWED];

        yield 'unlisted header' => ['POST', 'x-unlisted-header', Response::HTTP_BAD_REQUEST];
    }

    public static function apiErrors(): iterable
    {
        yield 'missing product' => ['GET', '/web/products/01994731-0123-7000-8000-000000000000', Response::HTTP_NOT_FOUND, null];

        yield 'invalid payload' => ['POST', '/web/products/', Response::HTTP_UNPROCESSABLE_ENTITY, '{"sku":""}'];
    }

    public static function nonApiPaths(): iterable
    {
        yield 'healthcheck' => ['/', Response::HTTP_OK];

        yield 'similar prefix' => ['/web-other', Response::HTTP_NOT_FOUND];
    }
}
