<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Presentation\Http\Web\Controller;

use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\ProductMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\ProductRepository;
use App\Catalog\Infrastructure\Presentation\Http\Web\Controller\ProductController;
use App\Catalog\Infrastructure\Presentation\Http\Web\Request\Product\CreateProductRequest;
use App\Catalog\Infrastructure\Presentation\Http\Web\Resource\Product as ProductResource;
use App\General\Adapter\Symfony\Identity\UuidGenerator;
use App\General\Identity\Id;
use App\General\Identity\IdGeneratorInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[CoversClass(ProductController::class)]
#[UsesClass(CreateProductRequest::class)]
#[UsesClass(DoctrineProduct::class)]
#[UsesClass(ProductMapper::class)]
#[UsesClass(ProductRepository::class)]
#[UsesClass(Product::class)]
#[UsesClass(ProductResource::class)]
#[UsesClass(Id::class)]
#[UsesClass(UuidGenerator::class)]
final class ProductControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private ProductRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
        $this->client->disableReboot();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();
        $this->repository = $container->get(ProductRepositoryInterface::class);
    }

    // ========================================================================
    // GET: returns persisted products or an empty catalog as JSON
    // ========================================================================

    public function testListReturnsProductsAsJson(): void
    {
        $first = $this->createProduct('product-1');
        $second = $this->createProduct('product-2');

        $this->client->request('GET', '/web/products/');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = $this->responseData();
        self::assertSame(['products'], array_keys($data));
        self::assertEqualsCanonicalizing([
            ['id' => $first->id->toString(), 'sku' => 'product-1'],
            ['id' => $second->id->toString(), 'sku' => 'product-2'],
        ], $data['products']);
    }

    public function testListReturnsEmptyCatalog(): void
    {
        $this->client->request('GET', '/web/products/');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(['products' => []], $this->responseData());
    }

    // ========================================================================
    // HEAD: returns JSON headers without a response body
    // ========================================================================

    public function testHeadReturnsHeadersWithoutBody(): void
    {
        $this->client->request('HEAD', '/web/products/');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame('', $this->client->getResponse()->getContent());
    }

    public function testShowHeadReturnsHeadersWithoutBody(): void
    {
        $product = $this->createProduct('product-1');

        $this->client->request('HEAD', '/web/products/'.$product->id);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame('', $this->client->getResponse()->getContent());
    }

    // ========================================================================
    // GET /{id}: loads a persisted product by its UUID
    // ========================================================================

    public function testShowReturnsProductAsJson(): void
    {
        $product = $this->createProduct('product-1');

        $this->client->request('GET', '/web/products/'.$product->id);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'product' => ['id' => $product->id->toString(), 'sku' => 'product-1'],
        ], $this->responseData());
    }

    public function testShowAcceptsUppercaseUuid(): void
    {
        $product = $this->createProduct('product-1');

        $this->client->request('GET', '/web/products/'.strtoupper($product->id->toString()));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([
            'product' => ['id' => $product->id->toString(), 'sku' => 'product-1'],
        ], $this->responseData());
    }

    // ========================================================================
    // POST: persists a valid SKU with a generated UUID v7
    // ========================================================================

    #[DataProvider('validSkus')]
    public function testCreatePersistsProduct(string $sku, string $expectedSku): void
    {
        $this->client->jsonRequest('POST', '/web/products/', ['sku' => $sku]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = $this->responseData();
        self::assertInstanceOf(UuidV7::class, Uuid::fromString($data['product']['id']));
        self::assertSame([
            'product' => ['id' => $data['product']['id'], 'sku' => $expectedSku],
        ], $data);

        $this->entityManager->clear();
        $saved = $this->repository->findById(Id::fromString($data['product']['id']));
        self::assertNotNull($saved);
        self::assertSame($data['product']['id'], $saved->id->toString());
        self::assertSame($expectedSku, $saved->sku);
        self::assertCount(1, $this->repository->findAll());
    }

    // ========================================================================
    // POST: rejects invalid payloads without inserting products
    // ========================================================================

    #[DataProvider('invalidJsonPayloads')]
    public function testCreateRejectsInvalidJson(string $payload): void
    {
        $this->client->request('POST', '/web/products/', server: ['CONTENT_TYPE' => 'application/json'], content: $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(Response::HTTP_BAD_REQUEST, $this->responseData()['status']);
        self::assertSame([], $this->repository->findAll());
    }

    #[DataProvider('invalidSkuPayloads')]
    public function testCreateRejectsInvalidSku(array $payload): void
    {
        $this->client->jsonRequest('POST', '/web/products/', $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = $this->responseData();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $data['status']);
        self::assertSame('sku', $data['violations'][0]['propertyPath']);
        self::assertSame([], $this->repository->findAll());
    }

    #[DataProvider('invalidPayloadStructures')]
    public function testCreateRejectsInvalidPayloadStructure(string $payload): void
    {
        $this->client->request('POST', '/web/products/', server: ['CONTENT_TYPE' => 'application/json'], content: $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->responseData()['status']);
        self::assertSame([], $this->repository->findAll());
    }

    #[DataProvider('oversizedSkus')]
    public function testCreateRejectsOversizedSku(string $sku): void
    {
        $this->client->jsonRequest('POST', '/web/products/', ['sku' => $sku]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = $this->responseData();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $data['status']);
        self::assertSame('sku', $data['violations'][0]['propertyPath']);
        self::assertSame('SKU must not exceed 255 characters.', $data['violations'][0]['title']);
        self::assertSame([], $this->repository->findAll());
    }

    // ========================================================================
    // POST: duplicate SKUs return a conflict and preserve existing data
    // ========================================================================

    #[DataProvider('duplicateSkus')]
    public function testCreateRejectsDuplicateSku(string $sku): void
    {
        $product = $this->createProduct('existing-product');

        $this->client->jsonRequest('POST', '/web/products/', ['sku' => $sku]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['error' => 'A product with this SKU already exists.'], $this->responseData());
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product'));

        $this->client->request('GET', '/web/products/'.$product->id);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([
            'product' => ['id' => $product->id->toString(), 'sku' => 'existing-product'],
        ], $this->responseData());
    }

    // ========================================================================
    // DELETE /{id}: hides the requested product while preserving its row and SKU
    // ========================================================================

    public function testDeleteHidesPersistedProduct(): void
    {
        $product = $this->createProduct('product-1');
        $other = $this->createProduct('product-2');

        $this->client->request('DELETE', '/web/products/'.$product->id);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());
        $this->entityManager->clear();
        self::assertNull($this->repository->findById($product->id));
        self::assertEquals([$other], $this->repository->findAll());
    }

    public function testDeletedProductReturnsNotFoundAndItsSkuRemainsReserved(): void
    {
        $product = $this->createProduct('reserved');
        $this->client->request('DELETE', '/web/products/'.$product->id);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        foreach (['GET', 'HEAD', 'DELETE'] as $method) {
            $this->client->request($method, '/web/products/'.$product->id);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }
        $this->client->request('GET', '/web/products/');
        self::assertResponseIsSuccessful();
        self::assertSame(['products' => []], $this->responseData());
        self::assertSame('reserved', $this->entityManager->getConnection()->fetchOne('SELECT sku FROM product WHERE id = ?', [$product->id->toString()]));

        $this->client->jsonRequest('POST', '/web/products/', ['sku' => 'reserved']);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    // ========================================================================
    // Unknown products: valid UUIDs without a matching product return 404
    // ========================================================================

    #[DataProvider('missingProducts')]
    public function testMissingProductReturnsNotFound(string $method, string $id): void
    {
        $product = $this->createProduct('existing-product');

        $this->client->request($method, '/web/products/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['error' => 'Product not found.'], $this->responseData());
        $this->entityManager->clear();
        self::assertEquals([$product], $this->repository->findAll());
    }

    // ========================================================================
    // Route requirements: malformed UUIDs never reach the controller
    // ========================================================================

    #[DataProvider('invalidProductIds')]
    public function testRouteRejectsInvalidUuid(string $method, string $id): void
    {
        $product = $this->createProduct('existing-product');

        $this->client->request($method, '/web/products/'.$id, server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->responseData()['status']);
        self::assertNull($this->client->getRequest()->attributes->get('_route'));
        $this->entityManager->clear();
        self::assertEquals([$product], $this->repository->findAll());
    }

    // ========================================================================
    // POST: rejects unsupported content types before saving a product
    // ========================================================================

    public function testCreateRejectsUnsupportedContentType(): void
    {
        $this->client->request('POST', '/web/products/', server: ['CONTENT_TYPE' => 'text/plain'], content: '{"sku":"product-1"}');

        self::assertResponseStatusCodeSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $this->responseData()['status']);
        self::assertSame([], $this->repository->findAll());
    }

    // ========================================================================
    // Persistence: create, read, list, and delete across HTTP requests
    // ========================================================================

    public function testProductLifecyclePersistsChanges(): void
    {
        $this->client->jsonRequest('POST', '/web/products/', ['sku' => 'new-product']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $product = $this->responseData()['product'];

        $this->entityManager->clear();
        $this->client->request('GET', '/web/products/'.$product['id']);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(['product' => $product], $this->responseData());

        $this->client->jsonRequest('POST', '/web/products/', ['sku' => 'another-product']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $other = $this->responseData()['product'];
        self::assertNotSame($product['id'], $other['id']);

        $this->client->request('GET', '/web/products/');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertEqualsCanonicalizing([$product, $other], $this->responseData()['products']);

        $this->client->request('DELETE', '/web/products/'.$product['id']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->client->request('GET', '/web/products/'.$product['id']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('DELETE', '/web/products/'.$product['id']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('GET', '/web/products/');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(['products' => [$other]], $this->responseData());

        $this->client->jsonRequest('POST', '/web/products/', ['sku' => 'new-product']);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    // ========================================================================
    // Unsupported methods: returns 405 and lists the allowed methods
    // ========================================================================

    #[DataProvider('unsupportedMethods')]
    public function testRejectsUnsupportedMethods(string $method, string $path, string $allowedMethods): void
    {
        $this->client->request($method, $path);

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
        self::assertResponseHeaderSame('Allow', $allowedMethods);
    }

    // ========================================================================
    // Failures: handle exceptions locally and keep internal details in logs
    // ========================================================================

    #[DataProvider('failingOperations')]
    public function testUnexpectedFailuresReturnSafeJson(string $method, string $path, string $operation): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->client->catchExceptions(false);
        $exception = new \RuntimeException('Sensitive database details.');
        $repository = $this->createStub(ProductRepositoryInterface::class);
        if ('findById' !== $operation) {
            $repository->method('findById')->willReturn(new Product(Id::fromString('01994731-abcd-7000-8000-000000000000'), 'test-product'));
        }
        $repository->method($operation)->willThrowException($exception);
        self::getContainer()->set(ProductRepositoryInterface::class, $repository);
        $logger = self::getContainer()->get(LoggerInterface::class);
        self::assertInstanceOf(Logger::class, $logger);
        $handler = new TestHandler(Level::Error, false);
        $logger->pushHandler($handler);

        $this->client->jsonRequest($method, $path, ['sku' => 'test-product']);

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['error' => 'Internal server error.'], $this->responseData());
        self::assertCount(1, $handler->getRecords());
        self::assertSame($exception, $handler->getRecords()[0]->context['exception']);
    }

    // ========================================================================
    // Helpers: persist fixtures and decode JSON responses
    // ========================================================================

    private function createProduct(string $sku): Product
    {
        $id = self::getContainer()->get(IdGeneratorInterface::class)->generate();
        $product = $this->repository->save(new Product(sku: $sku, id: $id));
        $this->entityManager->clear();

        return $product;
    }

    private function responseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function validSkus(): iterable
    {
        yield 'plain SKU' => ['new-product', 'new-product'];
        yield 'trimmed SKU' => ['  new-product  ', 'new-product'];
        yield 'zero string' => ['0', '0'];
        yield 'maximum length' => [str_repeat('a', 255), str_repeat('a', 255)];
        yield 'maximum length after trimming' => ['  '.str_repeat('a', 255).'  ', str_repeat('a', 255)];
        yield 'multibyte maximum length' => [str_repeat('é', 255), str_repeat('é', 255)];
    }

    public static function failingOperations(): iterable
    {
        $path = '/web/products/01994731-abcd-7000-8000-000000000000';

        yield 'list' => ['GET', '/web/products/', 'findAll'];
        yield 'show' => ['GET', $path, 'findById'];
        yield 'create' => ['POST', '/web/products/', 'save'];
        yield 'delete' => ['DELETE', $path, 'delete'];
    }

    public static function invalidJsonPayloads(): iterable
    {
        yield 'empty body' => [''];
        yield 'malformed JSON' => ['{"sku":'];
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

    public static function invalidPayloadStructures(): iterable
    {
        yield 'null' => ['null'];
        yield 'string' => ['"new-product"'];
        yield 'number' => ['123'];
        yield 'boolean' => ['true'];
        yield 'list' => ['[{"sku":"new-product"}]'];
    }

    public static function oversizedSkus(): iterable
    {
        yield 'ASCII' => [str_repeat('a', 256)];
        yield 'multibyte' => [str_repeat('é', 256)];
    }

    public static function duplicateSkus(): iterable
    {
        yield 'exact match' => ['existing-product'];
        yield 'trimmed match' => ['  existing-product  '];
    }

    public static function missingProducts(): iterable
    {
        yield 'GET unknown UUID' => ['GET', '01994731-0123-7000-8000-000000000000'];
        yield 'DELETE unknown UUID' => ['DELETE', '01994731-0123-7000-8000-000000000000'];
    }

    public static function invalidProductIds(): iterable
    {
        foreach (['GET', 'DELETE'] as $method) {
            yield $method.' malformed UUID' => [$method, 'unknown'];
            yield $method.' former mock ID' => [$method, '1'];
            yield $method.' invalid hex digit' => [$method, '01994731-0123-7000-8000-00000000000z'];
            yield $method.' invalid variant' => [$method, '01994731-0123-7000-0000-000000000000'];
        }
    }

    public static function unsupportedMethods(): iterable
    {
        yield 'PUT collection' => ['PUT', '/web/products/', 'GET, HEAD, POST'];
        yield 'PATCH collection' => ['PATCH', '/web/products/', 'GET, HEAD, POST'];
        yield 'DELETE collection' => ['DELETE', '/web/products/', 'GET, HEAD, POST'];
        yield 'OPTIONS collection' => ['OPTIONS', '/web/products/', 'GET, HEAD, POST'];
        yield 'POST product' => ['POST', '/web/products/01994731-0123-7000-8000-000000000000', 'GET, HEAD, DELETE'];
        yield 'PUT product' => ['PUT', '/web/products/01994731-0123-7000-8000-000000000000', 'GET, HEAD, DELETE'];
        yield 'PATCH product' => ['PATCH', '/web/products/01994731-0123-7000-8000-000000000000', 'GET, HEAD, DELETE'];
        yield 'OPTIONS product' => ['OPTIONS', '/web/products/01994731-0123-7000-8000-000000000000', 'GET, HEAD, DELETE'];
    }
}
