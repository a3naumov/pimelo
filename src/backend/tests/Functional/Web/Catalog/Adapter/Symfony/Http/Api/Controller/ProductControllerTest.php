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
    // GET /{id}: returns a specific product
    // ========================================================================

    #[DataProvider('existingProducts')]
    public function testShowReturnsProductAsJson(string $id, string $sku): void
    {
        $client = self::createClient();

        $client->request('GET', '/web/products/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'product' => ['id' => $id, 'sku' => $sku],
        ], json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testShowHeadReturnsHeadersWithoutBody(): void
    {
        $client = self::createClient();

        $client->request('HEAD', '/web/products/1');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame('', $client->getResponse()->getContent());
    }

    // ========================================================================
    // POST: creates a mock product from a valid SKU
    // ========================================================================

    #[DataProvider('validSkus')]
    public function testCreateReturnsMockProduct(string $sku, string $expectedSku): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', '/web/products/', ['sku' => $sku]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'product' => ['id' => '4', 'sku' => $expectedSku],
        ], json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    // ========================================================================
    // POST: rejects invalid JSON and missing or invalid SKUs
    // ========================================================================

    #[DataProvider('invalidJsonPayloads')]
    public function testCreateRejectsInvalidJson(string $payload): void
    {
        $client = self::createClient();

        $client->request('POST', '/web/products/', server: ['CONTENT_TYPE' => 'application/json'], content: $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'error' => 'Invalid JSON payload.',
        ], json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    #[DataProvider('invalidSkuPayloads')]
    public function testCreateRejectsInvalidSku(array $payload): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', '/web/products/', $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'error' => 'SKU must be a non-empty string.',
        ], json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    // ========================================================================
    // DELETE /{id}: acknowledges mock deletion without a response body
    // ========================================================================

    #[DataProvider('existingProductIds')]
    public function testDeleteReturnsNoContent(string $id): void
    {
        $client = self::createClient();

        $client->request('DELETE', '/web/products/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $client->getResponse()->getContent());
    }

    // ========================================================================
    // Unknown products: viewing or deleting a missing product returns 404
    // ========================================================================

    #[DataProvider('missingProducts')]
    public function testMissingProductReturnsNotFound(string $method, string $id): void
    {
        $client = self::createClient();

        $client->request($method, '/web/products/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'error' => 'Product not found.',
        ], json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    // ========================================================================
    // Mock state: creation and deletion do not change the fixture products
    // ========================================================================

    public function testMockOperationsDoNotPersistChanges(): void
    {
        $client = self::createClient();
        $client->request('GET', '/web/products/');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $initialProducts = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $client->jsonRequest('POST', '/web/products/', ['sku' => 'new-product']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('GET', '/web/products/4');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request('DELETE', '/web/products/1');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', '/web/products/1');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $client->request('GET', '/web/products/');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame($initialProducts, json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    // ========================================================================
    // Unsupported methods: returns 405 and lists the allowed methods
    // ========================================================================

    #[DataProvider('unsupportedMethods')]
    public function testRejectsUnsupportedMethods(string $method, string $path, string $allowedMethods): void
    {
        $client = self::createClient();

        $client->request($method, $path);

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
        self::assertResponseHeaderSame('Allow', $allowedMethods);
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function existingProducts(): iterable
    {
        yield 'first product' => ['1', 'product-1'];
        yield 'second product' => ['2', 'product-2'];
        yield 'third product' => ['3', 'product-3'];
    }

    public static function existingProductIds(): iterable
    {
        yield 'first product' => ['1'];
        yield 'second product' => ['2'];
        yield 'third product' => ['3'];
    }

    public static function validSkus(): iterable
    {
        yield 'plain SKU' => ['new-product', 'new-product'];
        yield 'trimmed SKU' => ['  new-product  ', 'new-product'];
        yield 'zero string' => ['0', '0'];
    }

    public static function invalidJsonPayloads(): iterable
    {
        yield 'empty body' => [''];
        yield 'malformed JSON' => ['{"sku":'];
        yield 'null' => ['null'];
        yield 'string' => ['"new-product"'];
        yield 'number' => ['123'];
        yield 'boolean' => ['true'];
    }

    public static function invalidSkuPayloads(): iterable
    {
        yield 'missing SKU' => [[]];
        yield 'null SKU' => [['sku' => null]];
        yield 'empty SKU' => [['sku' => '']];
        yield 'whitespace SKU' => [['sku' => " \t\n "]];
        yield 'integer SKU' => [['sku' => 123]];
        yield 'boolean SKU' => [['sku' => true]];
        yield 'array SKU' => [['sku' => ['new-product']]];
        yield 'object SKU' => [['sku' => (object) ['value' => 'new-product']]];
    }

    public static function missingProducts(): iterable
    {
        yield 'GET unknown ID' => ['GET', '999'];
        yield 'GET non-numeric ID' => ['GET', 'unknown'];
        yield 'DELETE unknown ID' => ['DELETE', '999'];
        yield 'DELETE non-numeric ID' => ['DELETE', 'unknown'];
    }

    public static function unsupportedMethods(): iterable
    {
        yield 'PUT collection' => ['PUT', '/web/products/', 'GET, HEAD, POST'];
        yield 'PATCH collection' => ['PATCH', '/web/products/', 'GET, HEAD, POST'];
        yield 'DELETE collection' => ['DELETE', '/web/products/', 'GET, HEAD, POST'];
        yield 'OPTIONS collection' => ['OPTIONS', '/web/products/', 'GET, HEAD, POST'];
        yield 'POST product' => ['POST', '/web/products/1', 'GET, HEAD, DELETE'];
        yield 'PUT product' => ['PUT', '/web/products/1', 'GET, HEAD, DELETE'];
        yield 'PATCH product' => ['PATCH', '/web/products/1', 'GET, HEAD, DELETE'];
        yield 'OPTIONS product' => ['OPTIONS', '/web/products/1', 'GET, HEAD, DELETE'];
    }
}
