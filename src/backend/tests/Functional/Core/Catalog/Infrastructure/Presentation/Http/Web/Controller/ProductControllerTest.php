<?php

declare(strict_types=1);

namespace App\Tests\Functional\Core\Catalog\Infrastructure\Presentation\Http\Web\Controller;

use App\Core\Catalog\Domain\Entity\Product;
use App\Core\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Mapper\ProductMapper;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Repository\ProductRepository;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Controller\ProductController;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Request\Product\CreateProductRequest;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Request\Product\UpdateProductRequest;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Resource\Product as ProductResource;
use App\Shared\General\Adapter\Symfony\Identity\UuidGenerator;
use App\Shared\General\Identity\Id;
use App\Shared\General\Identity\IdGeneratorInterface;
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
#[UsesClass(UpdateProductRequest::class)]
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
            ['id' => $first->id->toString(), 'sku' => 'product-1', 'deleted_at' => null],
            ['id' => $second->id->toString(), 'sku' => 'product-2', 'deleted_at' => null],
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
            'product' => ['id' => $product->id->toString(), 'sku' => 'product-1', 'deleted_at' => null],
        ], $this->responseData());
    }

    public function testShowAcceptsUppercaseUuid(): void
    {
        $product = $this->createProduct('product-1');

        $this->client->request('GET', '/web/products/'.strtoupper($product->id->toString()));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([
            'product' => ['id' => $product->id->toString(), 'sku' => 'product-1', 'deleted_at' => null],
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
            'product' => ['id' => $data['product']['id'], 'sku' => $expectedSku, 'deleted_at' => null],
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
            'product' => ['id' => $product->id->toString(), 'sku' => 'existing-product', 'deleted_at' => null],
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
    // PATCH: validates SKU and preserves product identity and category links
    // ========================================================================

    #[DataProvider('validSkus')]
    public function testUpdatePersistsSkuAndPreservesIdentity(string $sku, string $expectedSku): void
    {
        $product = $this->createProduct('original');

        $this->client->jsonRequest('PATCH', '/web/products/'.$product->id, ['sku' => $sku]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(['product' => ['id' => $product->id->toString(), 'sku' => $expectedSku, 'deleted_at' => null]], $this->responseData());
        $this->entityManager->clear();
        self::assertSame($expectedSku, $this->repository->findById($product->id)?->sku);
        self::assertCount(1, $this->repository->findAll());
    }

    public function testUpdatePreservesCategoryLinksAndAcceptsUnchangedSku(): void
    {
        $product = $this->createProduct('original');
        $categoryId = Uuid::v7()->toRfc4122();
        $this->connection->insert('category', ['id' => $categoryId, 'created_at' => '2026-01-01 00:00:00+00', 'updated_at' => '2026-01-01 00:00:00+00']);
        $this->connection->insert('product_category', ['product_id' => $product->id->toString(), 'category_id' => $categoryId]);

        foreach (['changed', 'changed'] as $sku) {
            $this->client->jsonRequest('PATCH', '/web/products/'.$product->id, ['sku' => $sku]);
            self::assertResponseStatusCodeSame(Response::HTTP_OK);
        }

        self::assertSame($categoryId, $this->connection->fetchOne('SELECT category_id FROM product_category WHERE product_id = ?', [$product->id->toString()]));
    }

    #[DataProvider('invalidSkuPayloads')]
    public function testUpdateRejectsInvalidSkuWithoutChangingProduct(array $payload): void
    {
        $product = $this->createProduct('original');

        $this->client->jsonRequest('PATCH', '/web/products/'.$product->id, $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('sku', $this->responseData()['violations'][0]['propertyPath']);
        self::assertSame('original', $this->repository->findById($product->id)?->sku);
    }

    #[DataProvider('oversizedSkus')]
    public function testUpdateRejectsOversizedSku(string $sku): void
    {
        $product = $this->createProduct('original');

        $this->client->jsonRequest('PATCH', '/web/products/'.$product->id, ['sku' => $sku]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('original', $this->repository->findById($product->id)?->sku);
    }

    #[DataProvider('reservedSkuStates')]
    public function testUpdateRejectsReservedSku(bool $deleted): void
    {
        $reserved = $this->createProduct('reserved');
        $product = $this->createProduct('original');

        if ($deleted) {
            $this->repository->delete($reserved);
        }

        $this->client->jsonRequest('PATCH', '/web/products/'.$product->id, ['sku' => ' reserved ']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame(['error' => 'A product with this SKU already exists.'], $this->responseData());
        self::assertSame('original', $this->connection->fetchOne('SELECT sku FROM product WHERE id = ?', [$product->id->toString()]));
    }

    public function testUpdateReturnsNotFoundForUnknownProduct(): void
    {
        $this->client->jsonRequest('PATCH', '/web/products/'.Uuid::v7()->toRfc4122(), ['sku' => 'new']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUpdateCannotRestoreDeletedProduct(): void
    {
        $product = $this->createProduct('original');
        $this->repository->delete($product);

        $this->client->jsonRequest('PATCH', '/web/products/'.$product->id, ['sku' => 'new']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('original', $this->connection->fetchOne('SELECT sku FROM product WHERE id = ?', [$product->id->toString()]));
    }

    #[DataProvider('invalidJsonPayloads')]
    public function testUpdateRejectsInvalidJson(string $payload): void
    {
        $product = $this->createProduct('original');

        $this->client->request('PATCH', '/web/products/'.$product->id, server: ['CONTENT_TYPE' => 'application/json'], content: $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testUpdateRequiresJson(): void
    {
        $product = $this->createProduct('original');

        $this->client->request('PATCH', '/web/products/'.$product->id, server: ['CONTENT_TYPE' => 'text/plain'], content: '{"sku":"new"}');

        self::assertResponseStatusCodeSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
    }

    // ========================================================================
    // Archived products: listing, viewing, restoring, and permanent deletion
    // ========================================================================

    public function testDeletedProductsAreAnExplicitListAndDetailOption(): void
    {
        $active = $this->createProduct('active');
        $deleted = $this->createProduct('deleted');
        $this->repository->delete($deleted);

        $this->client->request('GET', '/web/products/?status=deleted');
        self::assertResponseIsSuccessful();
        $products = $this->responseData()['products'];
        self::assertCount(1, $products);
        self::assertSame($deleted->id->toString(), $products[0]['id']);
        self::assertNotNull($products[0]['deleted_at']);

        $this->client->request('GET', '/web/products/'.$deleted->id.'?include_deleted=1');
        self::assertResponseIsSuccessful();
        self::assertSame(['product' => $products[0]], $this->responseData());

        $this->client->request('GET', '/web/products/'.$deleted->id);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->client->request('GET', '/web/products/?status=active');
        self::assertSame([$active->id->toString()], array_column($this->responseData()['products'], 'id'));
    }

    #[DataProvider('invalidListFilters')]
    public function testUnknownListFiltersShowActiveProducts(string $query): void
    {
        $active = $this->createProduct('active');
        $deleted = $this->createProduct('deleted');
        $this->repository->delete($deleted);

        $this->client->request('GET', '/web/products/?'.$query);

        self::assertResponseIsSuccessful();
        self::assertSame([$active->id->toString()], array_column($this->responseData()['products'], 'id'));
    }

    public function testUnknownDetailFilterKeepsDeletedProductHidden(): void
    {
        $product = $this->createProduct('deleted');
        $this->repository->delete($product);

        $this->client->request('GET', '/web/products/'.$product->id.'?include_deleted[]=1');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRestoreAndPermanentDeleteLifecycle(): void
    {
        $product = $this->createProduct('reserved');
        $id = $product->id->toString();
        $categoryId = Uuid::v7()->toRfc4122();
        $this->connection->insert('category', ['id' => $categoryId, 'created_at' => '2026-01-01 00:00:00+00', 'updated_at' => '2026-01-01 00:00:00+00']);
        $this->connection->insert('product_category', ['product_id' => $id, 'category_id' => $categoryId]);
        $this->repository->delete($product);

        $this->client->request('POST', '/web/products/'.$id.'/restore');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(['product' => ['id' => $id, 'sku' => 'reserved', 'deleted_at' => null]], $this->responseData());
        self::assertSame($categoryId, $this->connection->fetchOne('SELECT category_id FROM product_category WHERE product_id = ?', [$id]));
        self::assertNotNull($this->repository->findById($product->id));

        $this->client->request('DELETE', '/web/products/'.$id);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->client->request('DELETE', '/web/products/'.$id.'/permanent');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());
        self::assertFalse($this->connection->fetchOne('SELECT id FROM product WHERE id = ?', [$id]));
        self::assertFalse($this->connection->fetchOne('SELECT product_id FROM product_category WHERE product_id = ?', [$id]));
        self::assertSame($categoryId, $this->connection->fetchOne('SELECT id FROM category WHERE id = ?', [$categoryId]));
        $this->client->request('GET', '/web/products/'.$id.'?include_deleted=1');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->jsonRequest('POST', '/web/products/', ['sku' => 'reserved']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertNotSame($id, $this->responseData()['product']['id']);
    }

    #[DataProvider('archivedActions')]
    public function testArchivedActionsRejectActiveAndMissingProducts(string $method, string $suffix): void
    {
        $product = $this->createProduct('active');
        $this->client->request($method, '/web/products/'.$product->id.$suffix);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertEquals($product, $this->repository->findById($product->id));

        $this->client->request($method, '/web/products/'.Uuid::v7()->toRfc4122().$suffix);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
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

    public static function invalidListFilters(): iterable
    {
        yield 'unknown status' => ['status=all'];

        yield 'empty status' => ['status='];

        yield 'array status' => ['status[]=deleted'];
    }

    public static function archivedActions(): iterable
    {
        yield 'restore' => ['POST', '/restore'];

        yield 'permanent delete' => ['DELETE', '/permanent'];
    }

    public static function reservedSkuStates(): iterable
    {
        yield 'active product' => [false];

        yield 'deleted product' => [true];
    }

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

        yield 'restore' => ['POST', $path.'/restore', 'restore'];

        yield 'permanent delete' => ['DELETE', $path.'/permanent', 'deletePermanently'];

        yield 'list' => ['GET', '/web/products/', 'findAll'];

        yield 'show' => ['GET', $path, 'findById'];

        yield 'create' => ['POST', '/web/products/', 'save'];

        yield 'delete' => ['DELETE', $path, 'delete'];

        yield 'update lookup' => ['PATCH', $path, 'findById'];

        yield 'update save' => ['PATCH', $path, 'save'];
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

        yield 'POST product' => ['POST', '/web/products/01994731-0123-7000-8000-000000000000', 'GET, HEAD, PATCH, DELETE'];

        yield 'PUT product' => ['PUT', '/web/products/01994731-0123-7000-8000-000000000000', 'GET, HEAD, PATCH, DELETE'];

        yield 'OPTIONS product' => ['OPTIONS', '/web/products/01994731-0123-7000-8000-000000000000', 'GET, HEAD, PATCH, DELETE'];
    }
}
