<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web\Catalog\Adapter\Symfony\Http\Api\Controller;

use App\Web\Catalog\Adapter\Symfony\Http\Api\Controller\ProductController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ProductController::class)]
final class ProductControllerTest extends WebTestCase
{
    // ========================================================================
    // GET: returns the expected product list as JSON
    // ========================================================================

    public function testListReturnsProductsAsJson(): void
    {
        $client = self::createClient();

        $client->request('GET', '/web/products/');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'products' => [
                ['id' => '1', 'sku' => 'product-1'],
                ['id' => '2', 'sku' => 'product-2'],
                ['id' => '3', 'sku' => 'product-3'],
            ],
        ], json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    // ========================================================================
    // HEAD: returns JSON headers without a response body
    // ========================================================================

    public function testHeadReturnsHeadersWithoutBody(): void
    {
        $client = self::createClient();

        $client->request('HEAD', '/web/products/');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame('', $client->getResponse()->getContent());
    }

    // ========================================================================
    // Unsupported methods: returns 405 and lists the allowed methods
    // ========================================================================

    #[DataProvider('unsupportedMethods')]
    public function testListRejectsUnsupportedMethods(string $method): void
    {
        $client = self::createClient();

        $client->request($method, '/web/products/');

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
        self::assertResponseHeaderSame('Allow', 'GET, HEAD');
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function unsupportedMethods(): iterable
    {
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
        yield 'PATCH' => ['PATCH'];
        yield 'DELETE' => ['DELETE'];
        yield 'OPTIONS' => ['OPTIONS'];
    }
}
