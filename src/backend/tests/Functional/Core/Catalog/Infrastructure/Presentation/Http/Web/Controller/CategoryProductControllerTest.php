<?php

declare(strict_types=1);

namespace App\Tests\Functional\Core\Catalog\Infrastructure\Presentation\Http\Web\Controller;

use App\Core\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryCommand;
use App\Core\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryHandler;
use App\Core\Catalog\Domain\Entity\Category;
use App\Core\Catalog\Domain\Entity\Product;
use App\Core\Catalog\Domain\Hierarchy\CategoryAncestryResult;
use App\Core\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Core\Catalog\Domain\Persistence\Repository\ProductCategoryRepositoryInterface;
use App\Core\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use App\Core\Catalog\Domain\Service\Category\CategoryMover;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Hierarchy\DoctrineCategoryAncestry;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Mapper\ProductMapper;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Repository\CategoryRepository;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Repository\ProductCategoryRepository;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Repository\ProductRepository;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Transaction\DoctrineCategoryHierarchyTransaction;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Controller\CategoryController;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Controller\CategoryProductController;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Controller\ProductController;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Resource\Category as CategoryResource;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Resource\Product as ProductResource;
use App\Shared\General\Adapter\Symfony\Identity\UuidGenerator;
use App\Shared\General\Identity\Id;
use App\Shared\General\Identity\IdGeneratorInterface;
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

#[CoversClass(CategoryProductController::class)]
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
#[UsesClass(ProductResource::class)]
#[UsesClass(Id::class)]
#[UsesClass(UuidGenerator::class)]
final class CategoryProductControllerTest extends WebTestCase
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
        $this->category = $categories->save(new Category($generator->generate(), 'Category', $generator->generate()->toString()));
        $this->otherCategory = $categories->save(new Category($generator->generate(), 'Category', $generator->generate()->toString()));
        $container->get(EntityManagerInterface::class)->clear();
    }

    // ========================================================================
    // Lists: only direct active products are returned; old routes are removed
    // ========================================================================

    public function testListsOnlyDirectActiveProducts(): void
    {
        $this->link('PUT', $this->product, $this->category);
        $this->link('PUT', $this->otherProduct, $this->otherCategory);
        self::getContainer()->get(MoveCategoryHandler::class)(new MoveCategoryCommand($this->otherCategory->id, $this->category->id));
        $this->client->request('GET', '/web/categories/'.$this->category->id.'/products/');
        self::assertResponseIsSuccessful();
        self::assertSame(['products' => [['id' => $this->product->id->toString(), 'sku' => $this->product->sku, 'deleted_at' => null]]], $this->responseData());

        $this->client->request('DELETE', '/web/products/'.$this->product->id);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->client->request('GET', '/web/categories/'.$this->category->id.'/products/');
        self::assertSame(['products' => []], $this->responseData());
        $this->client->request('POST', '/web/products/'.$this->product->id.'/restore');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/web/categories/'.$this->category->id.'/products/');
        self::assertCount(1, $this->responseData()['products']);

        foreach (['GET', 'HEAD', 'PUT', 'DELETE'] as $method) {
            $this->client->request($method, '/web/products/'.$this->product->id.'/categories/'.(in_array($method, ['PUT', 'DELETE'], true) ? $this->category->id : ''));
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }
    }

    // ========================================================================
    // Query parameters: unknown include_deleted values keep active-only reads
    // ========================================================================

    #[DataProvider('unknownIncludeDeletedValues')]
    public function testUnknownIncludeDeletedValuesDoNotExposeDeletedProducts(string $query): void
    {
        $this->link('PUT', $this->product, $this->category);
        $this->link('PUT', $this->otherProduct, $this->category);
        $this->client->request('DELETE', '/web/products/'.$this->otherProduct->id);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->client->request('GET', '/web/categories/'.$this->category->id.'/products/?'.$query);

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['products' => [['id' => $this->product->id->toString(), 'sku' => $this->product->sku, 'deleted_at' => null]]],
            $this->responseData(),
        );

        $this->client->request('DELETE', '/web/categories/'.$this->category->id);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->client->request('GET', '/web/categories/'.$this->category->id.'/products/?'.$query);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
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

        $this->client->request('HEAD', '/web/categories/'.$this->category->id.'/products/');
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

        self::assertSame($this->category->id->toString(), $moved->id);
        self::assertSame($this->otherCategory->id->toString(), $moved->parentId);
        $movedCategory = $this->category->moveTo($this->otherCategory->id);
        $this->assertCategories($this->product, [$movedCategory]);
        $this->assertCategories($this->otherProduct, [$movedCategory]);
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
        $path = '/web/categories/'.$categoryId.'/products/'.('GET' === $method ? '' : $productId);

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

        $path = '/web/categories/'.$this->category->id.'/products/'.('GET' === $method ? '' : $this->product->id);
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
        $this->client->request('PUT', '/web/categories/'.strtoupper($this->category->id->toString()).'/products/'.strtoupper($this->product->id->toString()));

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
        $path = '/web/categories/'.$this->category->id.'/products/'.('GET' === $method ? '' : $this->product->id);

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
        $this->client->request($method, '/web/categories/'.$category->id.'/products/'.$product->id);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());
        self::getContainer()->get(EntityManagerInterface::class)->clear();
    }

    /**
     * @param list<Category> $categories
     */
    private function assertCategories(Product $product, array $categories): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertEqualsCanonicalizing($categories, self::getContainer()->get(ProductCategoryRepositoryInterface::class)->findCategories($product));
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
        yield 'GET missing category' => ['GET', false];

        foreach (['PUT', 'DELETE'] as $method) {
            yield $method.' missing product' => [$method, true];

            yield $method.' missing category' => [$method, false];
        }
    }

    public static function failingOperations(): iterable
    {
        yield 'list' => ['GET', 'findProducts'];

        yield 'attach' => ['PUT', 'attach'];

        yield 'detach' => ['DELETE', 'detach'];
    }

    public static function unknownIncludeDeletedValues(): iterable
    {
        yield 'unknown scalar' => ['include_deleted=invalid'];

        yield 'array value' => ['include_deleted%5B%5D=1'];
    }

    public static function invalidRequests(): iterable
    {
        foreach (['PUT', 'DELETE'] as $method) {
            foreach (['invalid', '01994731-abcd-7000-0000-000000000000'] as $id) {
                yield $method.' invalid product '.$id => [$method, '/web/categories/{category}/products/'.$id, Response::HTTP_NOT_FOUND, null];

                yield $method.' invalid category '.$id => [$method, '/web/categories/'.$id.'/products/{product}', Response::HTTP_NOT_FOUND, null];
            }
        }

        yield 'GET invalid product' => ['GET', '/web/categories/invalid/products/', Response::HTTP_NOT_FOUND, null];

        foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            yield $method.' collection' => [$method, '/web/categories/{category}/products/', Response::HTTP_METHOD_NOT_ALLOWED, 'GET, HEAD'];
        }

        foreach (['GET', 'POST', 'PATCH', 'OPTIONS'] as $method) {
            yield $method.' relation' => [$method, '/web/categories/{category}/products/{product}', Response::HTTP_METHOD_NOT_ALLOWED, 'PUT, DELETE'];
        }
    }
}
