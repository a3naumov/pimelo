<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Presentation\Http\Web\Controller;

use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryCommand;
use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryHandler;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Hierarchy\CategoryAncestryResult;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Persistence\Repository\ProductCategoryRepositoryInterface;
use App\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use App\Catalog\Domain\Service\Category\CategoryMover;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Catalog\Infrastructure\Persistence\Doctrine\Hierarchy\DoctrineCategoryAncestry;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\ProductMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\CategoryRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\ProductCategoryRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\ProductRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Transaction\DoctrineCategoryHierarchyTransaction;
use App\Catalog\Infrastructure\Presentation\Http\Web\Controller\CategoryController;
use App\Catalog\Infrastructure\Presentation\Http\Web\Controller\ProductCategoryController;
use App\Catalog\Infrastructure\Presentation\Http\Web\Controller\ProductController;
use App\Catalog\Infrastructure\Presentation\Http\Web\Resource\Category as CategoryResource;
use App\General\Adapter\Symfony\Identity\UuidGenerator;
use App\General\Identity\Id;
use App\General\Identity\IdGeneratorInterface;
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

#[CoversClass(ProductCategoryController::class)]
#[UsesClass(CategoryController::class)]
#[UsesClass(ProductController::class)]
#[UsesClass(DoctrineCategory::class)]
#[UsesClass(DoctrineProduct::class)]
#[UsesClass(CategoryMapper::class)]
#[UsesClass(ProductMapper::class)]
#[UsesClass(CategoryRepository::class)]
#[UsesClass(ProductRepository::class)]
#[UsesClass(ProductCategoryRepository::class)]
#[UsesClass(Category::class)]
#[UsesClass(DoctrineCategoryAncestry::class)]
#[UsesClass(DoctrineCategoryHierarchyTransaction::class)]
#[UsesClass(MoveCategoryCommand::class)]
#[UsesClass(MoveCategoryHandler::class)]
#[UsesClass(CategoryAncestryResult::class)]
#[UsesClass(CategoryMover::class)]
#[UsesClass(Product::class)]
#[UsesClass(CategoryResource::class)]
#[UsesClass(Id::class)]
#[UsesClass(UuidGenerator::class)]
final class ProductCategoryControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private Product $product;
    private Product $otherProduct;
    private Category $category;
    private Category $otherCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
        $this->client->disableReboot();
        $container = self::getContainer();
        $generator = $container->get(IdGeneratorInterface::class);
        $products = $container->get(ProductRepositoryInterface::class);
        $categories = $container->get(CategoryRepositoryInterface::class);
        $this->product = $products->save(new Product($generator->generate(), 'first'));
        $this->otherProduct = $products->save(new Product($generator->generate(), 'second'));
        $this->category = $categories->save(new Category($generator->generate()));
        $this->otherCategory = $categories->save(new Category($generator->generate()));
        $container->get(EntityManagerInterface::class)->clear();
    }

    // ========================================================================
    // Many-to-many: links are persistent, independent and idempotent
    // ========================================================================

    public function testManyToManyLifecycle(): void
    {
        $this->assertCategories($this->product, []);
        $this->link('PUT', $this->product, $this->category);
        $this->link('PUT', $this->product, $this->category);
        $this->link('PUT', $this->product, $this->otherCategory);
        $this->link('PUT', $this->otherProduct, $this->category);

        $this->assertCategories($this->product, [$this->category, $this->otherCategory]);
        $this->assertCategories($this->otherProduct, [$this->category]);

        $this->client->request('HEAD', '/web/products/'.$this->product->id.'/categories/');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame('', $this->client->getResponse()->getContent());

        $this->link('DELETE', $this->product, $this->category);
        $this->link('DELETE', $this->product, $this->category);
        $this->assertCategories($this->product, [$this->otherCategory]);
        $this->assertCategories($this->otherProduct, [$this->category]);

        $this->client->request('DELETE', '/web/categories/'.$this->category->id);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertCategories($this->otherProduct, []);
        $this->assertCategories($this->product, [$this->otherCategory]);

        $this->link('PUT', $this->otherProduct, $this->otherCategory);
        $this->client->request('DELETE', '/web/products/'.$this->product->id);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertCategories($this->otherProduct, [$this->otherCategory]);
        self::assertNotNull(self::getContainer()->get(CategoryRepositoryInterface::class)->findById($this->otherCategory->id));
    }

    // ========================================================================
    // Hierarchy: moving a category preserves every product association
    // ========================================================================

    public function testMovingCategoryPreservesProductLinks(): void
    {
        $this->link('PUT', $this->product, $this->category);
        $this->link('PUT', $this->otherProduct, $this->category);

        $moved = self::getContainer()->get(MoveCategoryHandler::class)(new MoveCategoryCommand($this->category->id, $this->otherCategory->id));

        $this->assertCategories($this->product, [$moved]);
        $this->assertCategories($this->otherProduct, [$moved]);
    }

    // ========================================================================
    // Missing resources: GET, PUT and DELETE return explicit 404 responses
    // ========================================================================

    #[DataProvider('missingResources')]
    public function testMissingResources(string $method, bool $missingProduct): void
    {
        $missingId = '01994731-abcd-7000-8000-000000000000';
        $productId = $missingProduct ? $missingId : $this->product->id->toString();
        $categoryId = $missingProduct ? $this->category->id->toString() : $missingId;
        $path = '/web/products/'.$productId.'/categories/'.('GET' === $method ? '' : $categoryId);

        $this->client->request($method, $path);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame(['error' => $missingProduct ? 'Product not found.' : 'Category not found.'], $this->responseData());
        $this->assertCategories($this->product, []);
    }

    // ========================================================================
    // Soft deletion: archived resources behave as missing, while links survive
    // ========================================================================

    #[DataProvider('missingResources')]
    public function testDeletedResourcesReturnNotFound(string $method, bool $deletedProduct): void
    {
        $this->link('PUT', $this->product, $this->category);
        $this->assertCategories($this->product, [$this->category]);
        $path = $deletedProduct
            ? '/web/products/'.$this->product->id
            : '/web/categories/'.$this->category->id;
        $this->client->request('DELETE', $path);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $path = '/web/products/'.$this->product->id.'/categories/'.('GET' === $method ? '' : $this->category->id);
        $this->client->request($method, $path);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame(['error' => $deletedProduct ? 'Product not found.' : 'Category not found.'], $this->responseData());
        self::assertSame(1, (int) self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT COUNT(*) FROM product_category'));
    }

    // ========================================================================
    // UUIDs: uppercase IDs are accepted; malformed IDs cannot reach actions
    // ========================================================================

    public function testUppercaseIdsAreAccepted(): void
    {
        $this->client->request('PUT', '/web/products/'.strtoupper($this->product->id->toString()).'/categories/'.strtoupper($this->category->id->toString()));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertCategories($this->product, [$this->category]);
    }

    #[DataProvider('invalidRequests')]
    public function testInvalidRequests(string $method, string $path, int $status, ?string $allow): void
    {
        $path = str_replace(['{product}', '{category}'], [$this->product->id->toString(), $this->category->id->toString()], $path);

        $this->client->request($method, $path, server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame($status);
        if (null !== $allow) {
            self::assertResponseHeaderSame('Allow', $allow);
        }
        $this->assertCategories($this->product, []);
    }

    // ========================================================================
    // Failures: handle exceptions locally and keep internal details in logs
    // ========================================================================

    #[DataProvider('failingOperations')]
    public function testUnexpectedFailuresReturnSafeJson(string $method, string $operation): void
    {
        $exception = new \RuntimeException('Sensitive database details.');
        $repository = $this->createStub(ProductCategoryRepositoryInterface::class);
        $repository->method($operation)->willThrowException($exception);
        self::getContainer()->set(ProductCategoryRepositoryInterface::class, $repository);
        $logger = self::getContainer()->get(LoggerInterface::class);
        self::assertInstanceOf(Logger::class, $logger);
        $handler = new TestHandler(Level::Error, false);
        $logger->pushHandler($handler);
        $this->client->catchExceptions(false);
        $path = '/web/products/'.$this->product->id.'/categories/'.('GET' === $method ? '' : $this->category->id);

        $this->client->request($method, $path);

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['error' => 'Internal server error.'], $this->responseData());
        self::assertCount(1, $handler->getRecords());
        self::assertSame($exception, $handler->getRecords()[0]->context['exception']);
    }

    // ========================================================================
    // Helpers: reload relations from PostgreSQL between requests
    // ========================================================================

    private function link(string $method, Product $product, Category $category): void
    {
        $this->client->request($method, '/web/products/'.$product->id.'/categories/'.$category->id);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());
        self::getContainer()->get(EntityManagerInterface::class)->clear();
    }

    /** @param list<Category> $categories */
    private function assertCategories(Product $product, array $categories): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->client->request('GET', '/web/products/'.$product->id.'/categories/');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = $this->responseData();
        self::assertSame(['categories'], array_keys($data));
        self::assertEqualsCanonicalizing(array_map(static fn (Category $category): array => ['id' => $category->id->toString(), 'parent_id' => $category->parentId?->toString()], $categories), $data['categories']);
    }

    private function responseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function missingResources(): iterable
    {
        yield 'GET missing product' => ['GET', true];
        foreach (['PUT', 'DELETE'] as $method) {
            yield $method.' missing product' => [$method, true];
            yield $method.' missing category' => [$method, false];
        }
    }

    public static function failingOperations(): iterable
    {
        yield 'list' => ['GET', 'findCategories'];
        yield 'attach' => ['PUT', 'attach'];
        yield 'detach' => ['DELETE', 'detach'];
    }

    public static function invalidRequests(): iterable
    {
        foreach (['PUT', 'DELETE'] as $method) {
            foreach (['invalid', '01994731-abcd-7000-0000-000000000000'] as $id) {
                yield $method.' invalid product '.$id => [$method, '/web/products/'.$id.'/categories/{category}', Response::HTTP_NOT_FOUND, null];
                yield $method.' invalid category '.$id => [$method, '/web/products/{product}/categories/'.$id, Response::HTTP_NOT_FOUND, null];
            }
        }
        yield 'GET invalid product' => ['GET', '/web/products/invalid/categories/', Response::HTTP_NOT_FOUND, null];
        foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            yield $method.' collection' => [$method, '/web/products/{product}/categories/', Response::HTTP_METHOD_NOT_ALLOWED, 'GET, HEAD'];
        }
        foreach (['GET', 'POST', 'PATCH', 'OPTIONS'] as $method) {
            yield $method.' relation' => [$method, '/web/products/{product}/categories/{category}', Response::HTTP_METHOD_NOT_ALLOWED, 'PUT, DELETE'];
        }
    }
}
