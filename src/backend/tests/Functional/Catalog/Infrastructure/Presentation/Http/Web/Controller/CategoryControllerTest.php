<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Presentation\Http\Web\Controller;

use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryCommand;
use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryHandler;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Domain\Hierarchy\CategoryAncestryResult;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Service\Category\CategoryMover;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Hierarchy\DoctrineCategoryAncestry;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\CategoryRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Transaction\DoctrineCategoryHierarchyTransaction;
use App\Catalog\Infrastructure\Presentation\Http\Web\Controller\CategoryController;
use App\Catalog\Infrastructure\Presentation\Http\Web\Request\Category\MoveCategoryRequest;
use App\Catalog\Infrastructure\Presentation\Http\Web\Resource\Category as CategoryResource;
use App\General\Adapter\Symfony\Identity\UuidGenerator;
use App\General\Identity\Id;
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

#[CoversClass(CategoryController::class)]
#[UsesClass(MoveCategoryRequest::class)]
#[UsesClass(CategoryNotFoundException::class)]
#[UsesClass(InvalidCategoryHierarchyException::class)]
#[UsesClass(DoctrineCategory::class)]
#[UsesClass(CategoryMapper::class)]
#[UsesClass(CategoryRepository::class)]
#[UsesClass(Category::class)]
#[UsesClass(DoctrineCategoryAncestry::class)]
#[UsesClass(DoctrineCategoryHierarchyTransaction::class)]
#[UsesClass(MoveCategoryCommand::class)]
#[UsesClass(MoveCategoryHandler::class)]
#[UsesClass(CategoryAncestryResult::class)]
#[UsesClass(CategoryMover::class)]
#[UsesClass(CategoryResource::class)]
#[UsesClass(Id::class)]
#[UsesClass(UuidGenerator::class)]
final class CategoryControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
        $this->client->disableReboot();
    }

    // ========================================================================
    // Lifecycle: creates a root category without a request body
    // ========================================================================

    public function testCategoryLifecycle(): void
    {
        $this->client->request('GET', '/web/categories/');
        self::assertResponseIsSuccessful();
        self::assertSame(['categories' => []], $this->responseData());

        $this->client->request('POST', '/web/categories/');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = $this->responseData();
        self::assertSame(['category'], array_keys($data));
        self::assertSame(['id', 'parent_id'], array_keys($data['category']));
        self::assertNull($data['category']['parent_id']);
        $id = $data['category']['id'];
        self::assertInstanceOf(UuidV7::class, Uuid::fromString($id));

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $saved = self::getContainer()->get(CategoryRepositoryInterface::class)->findById(Id::fromString($id));
        self::assertNotNull($saved);
        self::assertSame($id, $saved->id->toString());

        $this->client->request('GET', '/web/categories/'.strtoupper($id));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame($data, $this->responseData());

        $this->client->request('POST', '/web/categories/');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $other = $this->responseData()['category'];
        self::assertNotSame($id, $other['id']);

        $this->client->request('GET', '/web/categories/');
        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing([$data['category'], $other], $this->responseData()['categories']);

        foreach (['/web/categories/', '/web/categories/'.$id] as $path) {
            $this->client->request('HEAD', $path);
            self::assertResponseStatusCodeSame(Response::HTTP_OK);
            self::assertResponseHeaderSame('Content-Type', 'application/json');
            self::assertSame('', $this->client->getResponse()->getContent());
        }

        $this->client->request('DELETE', '/web/categories/'.$id);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());

        foreach (['GET', 'DELETE'] as $method) {
            $this->client->request($method, '/web/categories/'.$id);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
            self::assertSame(['error' => 'Category not found.'], $this->responseData());
        }

        $this->client->request('GET', '/web/categories/');
        self::assertSame(['categories' => [$other]], $this->responseData());
    }

    // ========================================================================
    // Moving: reparenting preserves descendants and supports returning to root
    // ========================================================================

    public function testMoveBetweenBranchesAndBackToRoot(): void
    {
        $firstRoot = $this->createCategory();
        $secondRoot = $this->createCategory();
        $child = $this->createCategory();
        $grandchild = $this->createCategory();

        $this->moveCategory($child, $firstRoot);
        $this->moveCategory($grandchild, $child);
        $this->moveCategory($child, $secondRoot);
        $this->moveCategory($child, $secondRoot);

        $this->assertParent($grandchild, $child);
        $this->assertParent($child, $secondRoot);
        $this->client->request('GET', '/web/categories/');
        self::assertEqualsCanonicalizing([
            ['id' => $firstRoot, 'parent_id' => null],
            ['id' => $secondRoot, 'parent_id' => null],
            ['id' => $child, 'parent_id' => $secondRoot],
            ['id' => $grandchild, 'parent_id' => $child],
        ], $this->responseData()['categories']);

        $this->moveCategory($child, null);
        $this->moveCategory($child, null);
        $this->assertParent($child, null);
        $this->assertParent($grandchild, $child);
    }

    // ========================================================================
    // Cycles: rejected moves leave data and the next request usable
    // ========================================================================

    public function testRejectsSelfAndDescendantMovesWithoutBreakingNextRequest(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory();
        $grandchild = $this->createCategory();
        $this->moveCategory($child, $root);
        $this->moveCategory($grandchild, $child);

        foreach ([$root, $child, $grandchild] as $parent) {
            $this->client->jsonRequest('PATCH', '/web/categories/'.$root, ['parent_id' => $parent]);

            self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
            self::assertArrayHasKey('error', $this->responseData());
            self::assertTrue(self::getContainer()->get(EntityManagerInterface::class)->isOpen());
            $this->assertParent($root, null);
            $this->assertParent($child, $root);
        }

        $this->moveCategory($grandchild, $root);
    }

    // ========================================================================
    // Soft deletion: removes the entire subtree from the API, not the database
    // ========================================================================

    public function testParentDeletionHidesTheSubtreeAndRejectsFurtherOperations(): void
    {
        $parent = $this->createCategory();
        $child = $this->createCategory();
        $leaf = $this->createCategory();
        $other = $this->createCategory();
        $this->moveCategory($child, $parent);
        $this->moveCategory($leaf, $child);

        $this->client->request('DELETE', '/web/categories/'.$parent);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());
        foreach ([$parent, $child, $leaf] as $id) {
            foreach (['GET', 'HEAD', 'DELETE'] as $method) {
                $this->client->request($method, '/web/categories/'.$id);
                self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
            }
            $this->client->jsonRequest('PATCH', '/web/categories/'.$id, ['parent_id' => null]);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }
        $this->client->jsonRequest('PATCH', '/web/categories/'.$other, ['parent_id' => $parent]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertParent($other, null);
        $this->client->request('GET', '/web/categories/');
        self::assertResponseIsSuccessful();
        self::assertSame(['categories' => [['id' => $other, 'parent_id' => null]]], $this->responseData());
        self::assertSame(4, (int) self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT COUNT(*) FROM category'));
    }

    // ========================================================================
    // Missing records and UUID normalization
    // ========================================================================

    public function testMissingCategoriesReturnNotFoundAndUppercaseIdsWork(): void
    {
        $category = $this->createCategory();
        $parent = $this->createCategory();
        $missing = '01994731-abcd-7000-8000-000000000000';

        $this->client->jsonRequest('PATCH', '/web/categories/'.$category, ['parent_id' => $missing]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame(['error' => 'Parent category not found.'], $this->responseData());
        $this->assertParent($category, null);

        $this->client->jsonRequest('PATCH', '/web/categories/'.$missing, ['parent_id' => null]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame(['error' => 'Category not found.'], $this->responseData());

        $this->client->jsonRequest('PATCH', '/web/categories/'.strtoupper($category), ['parent_id' => strtoupper($parent)]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(['category' => ['id' => $category, 'parent_id' => $parent]], $this->responseData());
    }

    // ========================================================================
    // Validation: missing parent_id differs from an explicit null
    // ========================================================================

    #[DataProvider('invalidMovePayloads')]
    public function testInvalidMovePayloadsLeaveParentUnchanged(string $payload, int $status): void
    {
        $parent = $this->createCategory();
        $child = $this->createCategory();
        $this->moveCategory($child, $parent);

        $this->client->request('PATCH', '/web/categories/'.$child, server: ['CONTENT_TYPE' => 'application/json'], content: $payload);

        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertParent($child, $parent);
    }

    public function testMoveRejectsUnsupportedContentType(): void
    {
        $category = $this->createCategory();

        $this->client->request('PATCH', '/web/categories/'.$category, server: ['CONTENT_TYPE' => 'text/plain'], content: '{"parent_id":null}');

        self::assertResponseStatusCodeSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        $this->assertParent($category, null);
    }

    // ========================================================================
    // Routing: malformed IDs and unsupported methods do not reach the actions
    // ========================================================================

    #[DataProvider('invalidRequests')]
    public function testInvalidRequests(string $method, string $path, int $status, ?string $allow): void
    {
        $this->client->request($method, $path, server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame($status);
        if (null !== $allow) {
            self::assertResponseHeaderSame('Allow', $allow);
        }
    }

    // ========================================================================
    // Failures: handle exceptions locally and keep internal details in logs
    // ========================================================================

    #[DataProvider('failingOperations')]
    public function testUnexpectedFailuresReturnSafeJson(string $method, string $path, string $operation, bool $phpError = false): void
    {
        $exception = $phpError ? new \TypeError('Sensitive internal details.') : new \RuntimeException('Sensitive database details.');
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        if ('findById' !== $operation) {
            $repository->method('findById')->willReturn(new Category(Id::fromString('01994731-abcd-7000-8000-000000000000')));
        }
        $repository->method($operation)->willThrowException($exception);
        self::getContainer()->set(CategoryRepositoryInterface::class, $repository);
        $logger = self::getContainer()->get(LoggerInterface::class);
        self::assertInstanceOf(Logger::class, $logger);
        $handler = new TestHandler(Level::Error, false);
        $logger->pushHandler($handler);
        $this->client->catchExceptions(false);

        $this->client->jsonRequest($method, $path, ['parent_id' => null]);

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['error' => 'Internal server error.'], $this->responseData());
        self::assertCount(1, $handler->getRecords());
        self::assertSame($exception, $handler->getRecords()[0]->context['exception']);
    }

    #[DataProvider('creationConflicts')]
    public function testCreationHandlesDomainExceptions(\Throwable $exception, int $status): void
    {
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('save')->willThrowException($exception);
        self::getContainer()->set(CategoryRepositoryInterface::class, $repository);
        $this->client->catchExceptions(false);

        $this->client->request('POST', '/web/categories/');

        self::assertResponseStatusCodeSame($status);
        self::assertSame(['error' => $exception->getMessage()], $this->responseData());
    }

    // ========================================================================
    // Helpers: reload persisted parents between HTTP requests
    // ========================================================================

    private function createCategory(): string
    {
        $this->client->request('POST', '/web/categories/');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $this->responseData()['category']['id'];
    }

    private function moveCategory(string $id, ?string $parentId): void
    {
        $this->client->jsonRequest('PATCH', '/web/categories/'.$id, ['parent_id' => $parentId]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(['category' => ['id' => $id, 'parent_id' => $parentId]], $this->responseData());
        self::getContainer()->get(EntityManagerInterface::class)->clear();
    }

    private function assertParent(string $id, ?string $parentId): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->client->request('GET', '/web/categories/'.$id);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(['category' => ['id' => $id, 'parent_id' => $parentId]], $this->responseData());
    }

    private function responseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function invalidRequests(): iterable
    {
        foreach (['GET', 'DELETE', 'PATCH'] as $method) {
            foreach (['invalid', '1', '01994731-abcd-7000-0000-000000000000'] as $id) {
                yield $method.' '.$id => [$method, '/web/categories/'.$id, Response::HTTP_NOT_FOUND, null];
            }
        }
        foreach (['PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            yield $method.' collection' => [$method, '/web/categories/', Response::HTTP_METHOD_NOT_ALLOWED, 'GET, HEAD, POST'];
        }
        foreach (['POST', 'PUT', 'OPTIONS'] as $method) {
            yield $method.' category' => [$method, '/web/categories/01994731-abcd-7000-8000-000000000000', Response::HTTP_METHOD_NOT_ALLOWED, 'GET, HEAD, PATCH, DELETE'];
        }
    }

    public static function failingOperations(): iterable
    {
        $path = '/web/categories/01994731-abcd-7000-8000-000000000000';

        yield 'list' => ['GET', '/web/categories/', 'findAll'];
        yield 'show' => ['GET', $path, 'findById'];
        yield 'create' => ['POST', '/web/categories/', 'save'];
        yield 'move' => ['PATCH', $path, 'save'];
        yield 'delete' => ['DELETE', $path, 'delete'];
        yield 'PHP error' => ['GET', '/web/categories/', 'findAll', true];
    }

    public static function creationConflicts(): iterable
    {
        yield 'missing category' => [new CategoryNotFoundException('Parent category not found.'), Response::HTTP_NOT_FOUND];
        yield 'invalid hierarchy' => [new InvalidCategoryHierarchyException('Invalid category hierarchy.'), Response::HTTP_CONFLICT];
    }

    public static function invalidMovePayloads(): iterable
    {
        yield 'missing parent' => ['{}', Response::HTTP_UNPROCESSABLE_ENTITY];
        yield 'null payload' => ['null', Response::HTTP_UNPROCESSABLE_ENTITY];
        yield 'list payload' => ['[{"parent_id":null}]', Response::HTTP_UNPROCESSABLE_ENTITY];
        yield 'empty string' => ['{"parent_id":""}', Response::HTTP_UNPROCESSABLE_ENTITY];
        yield 'invalid UUID' => ['{"parent_id":"invalid"}', Response::HTTP_UNPROCESSABLE_ENTITY];
        yield 'integer' => ['{"parent_id":42}', Response::HTTP_UNPROCESSABLE_ENTITY];
        yield 'boolean' => ['{"parent_id":false}', Response::HTTP_UNPROCESSABLE_ENTITY];
        yield 'array' => ['{"parent_id":[]}', Response::HTTP_UNPROCESSABLE_ENTITY];
        yield 'object' => ['{"parent_id":{}}', Response::HTTP_UNPROCESSABLE_ENTITY];
        yield 'invalid JSON' => ['{"parent_id":', Response::HTTP_BAD_REQUEST];
    }
}
