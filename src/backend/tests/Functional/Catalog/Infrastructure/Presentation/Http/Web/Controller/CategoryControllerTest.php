<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Infrastructure\Presentation\Http\Web\Controller;

use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryCommand;
use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryHandler;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Domain\Hierarchy\CategoryAncestryResult;
use App\Catalog\Domain\Hierarchy\CategoryBranch;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Service\Category\CategoryMover;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Hierarchy\DoctrineCategoryAncestry;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\CategoryRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Transaction\DoctrineCategoryHierarchyTransaction;
use App\Catalog\Infrastructure\Presentation\Http\Web\Controller\CategoryController;
use App\Catalog\Infrastructure\Presentation\Http\Web\Request\Category\CreateCategoryRequest;
use App\Catalog\Infrastructure\Presentation\Http\Web\Request\Category\ListCategoriesRequest;
use App\Catalog\Infrastructure\Presentation\Http\Web\Request\Category\MoveCategoryRequest;
use App\Catalog\Infrastructure\Presentation\Http\Web\Resource\Category as CategoryResource;
use App\Catalog\Infrastructure\Presentation\Http\Web\Resource\CategoryBranch as CategoryBranchResource;
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
#[UsesClass(CreateCategoryRequest::class)]
#[UsesClass(ListCategoriesRequest::class)]
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
#[UsesClass(CategoryBranchResource::class)]
#[UsesClass(CategoryBranch::class)]
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
    // Branch: complete ancestor levels with a constant number of database reads
    // ========================================================================

    public function testLoadsDeepBranchWithSiblingsInTwoQueries(): void
    {
        $root = $this->createCategory();
        $other = $this->createCategory();
        $unrelated = $this->createCategory();
        $this->moveCategory($unrelated, $other);
        $path = [$root];
        $siblings = [];

        for ($depth = 0; $depth < 9; ++$depth) {
            $parent = $path[array_key_last($path)];
            $child = $this->createCategory();
            $sibling = $this->createCategory();
            $this->moveCategory($child, $parent);
            $this->moveCategory($sibling, $parent);
            $path[] = $child;
            $siblings[] = $sibling;
        }

        $selected = $path[array_key_last($path)];
        $unloaded = $this->createCategory();
        $this->moveCategory($unloaded, $selected);
        $holder = self::getContainer()->get('doctrine.debug_data_holder');

        foreach ([$root, $selected] as $id) {
            $holder->reset();
            $this->client->request('GET', '/web/categories/'.$id.'/branch');
            self::assertResponseIsSuccessful();
            self::assertSame(2, array_sum(array_map('count', $holder->getData())));
            $data = $this->responseData();
            $expectedPath = $id === $root ? [$root] : $path;
            self::assertSame($expectedPath, array_column($data['path'], 'id'));
            self::assertCount(count($expectedPath), $data['levels']);
            self::assertNull($data['levels'][0]['parent_id']);
            self::assertEqualsCanonicalizing([$root, $other], array_column($data['levels'][0]['categories'], 'id'));

            foreach (array_slice($data['levels'], 1) as $index => $level) {
                self::assertSame($path[$index], $level['parent_id']);
                self::assertEqualsCanonicalizing([$path[$index + 1], $siblings[$index]], array_column($level['categories'], 'id'));
            }

            $loadedIds = array_column(array_merge(...array_column($data['levels'], 'categories')), 'id');
            self::assertNotContains($unloaded, $loadedIds);
            self::assertNotContains($unrelated, $loadedIds);
            self::assertTrue($data['path'][array_key_last($data['path'])]['has_children']);
        }

        $this->client->request('HEAD', '/web/categories/'.strtoupper($selected).'/branch');
        self::assertResponseIsSuccessful();
        self::assertSame('', $this->client->getResponse()->getContent());
    }

    public function testBranchExcludesDeletedSiblingsAndRejectsMissingCategories(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory();
        $this->moveCategory($child, $root);
        $this->client->request('DELETE', '/web/categories/'.$child);
        $this->client->request('GET', '/web/categories/'.$root.'/branch');
        self::assertResponseIsSuccessful();
        self::assertFalse($this->responseData()['path'][0]['has_children']);
        self::assertCount(1, $this->responseData()['levels'][0]['categories']);

        foreach ([$child, '01994731-abcd-7000-8000-000000000000', 'invalid'] as $id) {
            $this->client->request('GET', '/web/categories/'.$id.'/branch');
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }
    }

    public function testBranchRejectsCyclesAndInactiveAncestors(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory();
        $this->moveCategory($child, $root);
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $connection->update('category', ['parent_id' => $child], ['id' => $root]);
        $this->client->request('GET', '/web/categories/'.$child.'/branch');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertArrayNotHasKey('path', $this->responseData());
        $connection->update('category', ['parent_id' => null, 'deleted_at' => '2026-01-01 00:00:00+00'], ['id' => $root]);
        $this->client->request('GET', '/web/categories/'.$child.'/branch');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertArrayNotHasKey('levels', $this->responseData());
    }

    // ========================================================================
    // Levels: roots and immediate children expose active child availability
    // ========================================================================

    public function testLoadsOneLevelAndUpdatesChildAvailabilityAfterDeletion(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory();
        $leaf = $this->createCategory();
        $this->moveCategory($child, $root);
        $this->moveCategory($leaf, $child);

        $this->client->request('GET', '/web/categories/');
        self::assertSame(['categories' => [['id' => $root, 'parent_id' => null, 'has_children' => true, 'deleted_at' => null]]], $this->responseData());
        $this->client->request('GET', '/web/categories/?parent_id='.$root);
        self::assertSame(['categories' => [['id' => $child, 'parent_id' => $root, 'has_children' => true, 'deleted_at' => null]]], $this->responseData());
        $this->client->request('GET', '/web/categories/?parent_id='.strtoupper($root));
        self::assertSame([$child], array_column($this->responseData()['categories'], 'id'));
        $this->client->request('GET', '/web/categories/?parent_id='.$leaf);
        self::assertSame(['categories' => []], $this->responseData());
        $this->client->request('HEAD', '/web/categories/?parent_id='.$root);
        self::assertResponseIsSuccessful();
        self::assertSame('', $this->client->getResponse()->getContent());

        $this->client->request('DELETE', '/web/categories/'.$child);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->client->request('GET', '/web/categories/'.$root);
        self::assertFalse($this->responseData()['category']['has_children']);
        $this->client->request('GET', '/web/categories/?parent_id='.$root);
        self::assertSame(['categories' => []], $this->responseData());

        foreach ([$child, $leaf, '01994731-abcd-7000-8000-000000000000'] as $id) {
            $this->client->request('GET', '/web/categories/?parent_id='.$id);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }

        foreach (['invalid', '', 'null', '123'] as $id) {
            $this->client->request('GET', '/web/categories/?parent_id='.$id);
            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->client->request('GET', '/web/categories/?parent_id[]=invalid');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->client->request('HEAD', '/web/categories/?parent_id=invalid');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/web/categories/'.$root.'/children/');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ========================================================================
    // Creation: optional parents are validated and persisted in the initial write
    // ========================================================================

    public function testCreatesCategoryWithSelectedParent(): void
    {
        $parent = $this->createCategory();
        $this->client->jsonRequest('POST', '/web/categories/', ['parent_id' => $parent]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $child = $this->responseData()['category']['id'];
        self::assertSame($parent, $this->responseData()['category']['parent_id']);
        $this->assertParent($child, $parent);

        $this->client->request('GET', '/web/categories/?parent_id='.$parent);
        self::assertSame([$child], array_column($this->responseData()['categories'], 'id'));
        $this->client->request('GET', '/web/categories/');
        self::assertSame([['id' => $parent, 'parent_id' => null, 'has_children' => true, 'deleted_at' => null]], $this->responseData()['categories']);
    }

    public function testCreatesRootWithExplicitNullParent(): void
    {
        $this->client->jsonRequest('POST', '/web/categories/', ['parent_id' => null]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertNull($this->responseData()['category']['parent_id']);
    }

    public function testRejectsMissingAndDeletedCreationParentsWithoutCreatingARoot(): void
    {
        $deleted = $this->createCategory();
        $this->client->request('DELETE', '/web/categories/'.$deleted);

        foreach ([$deleted, '01994731-abcd-7000-8000-000000000000'] as $parent) {
            $this->client->jsonRequest('POST', '/web/categories/', ['parent_id' => $parent]);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
            $this->client->request('GET', '/web/categories/');
            self::assertSame([], $this->responseData()['categories']);
        }
    }

    #[DataProvider('invalidCreationPayloads')]
    public function testRejectsInvalidCreationParent(string $payload, int $status): void
    {
        $this->client->request('POST', '/web/categories/', server: ['CONTENT_TYPE' => 'application/json'], content: $payload);
        self::assertResponseStatusCodeSame($status);
        $this->client->request('GET', '/web/categories/');
        self::assertSame([], $this->responseData()['categories']);
    }

    // ========================================================================
    // Lifecycle: omitted request bodies remain compatible with root creation
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
        self::assertSame(['id', 'parent_id', 'has_children', 'deleted_at'], array_keys($data['category']));
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
            ['id' => $firstRoot, 'parent_id' => null, 'has_children' => false, 'deleted_at' => null],
            ['id' => $secondRoot, 'parent_id' => null, 'has_children' => true, 'deleted_at' => null],
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
        self::assertSame(['categories' => [['id' => $other, 'parent_id' => null, 'has_children' => false, 'deleted_at' => null]]], $this->responseData());
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
        self::assertSame(['category' => ['id' => $category, 'parent_id' => $parent, 'has_children' => false, 'deleted_at' => null]], $this->responseData());
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

    // ========================================================================
    // Deleted hierarchy: visibility, restoration and permanent deletion
    // ========================================================================

    public function testDeletedHierarchyReadsRespectVisibilityAndRejectInvalidModes(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory();
        $this->moveCategory($child, $root);
        $this->client->request('DELETE', '/web/categories/'.$child);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->client->request('GET', '/web/categories/');
        self::assertFalse($this->responseData()['categories'][0]['has_children']);
        $this->client->request('GET', '/web/categories/?include_deleted=1');
        self::assertTrue($this->responseData()['categories'][0]['has_children']);
        $this->client->request('GET', '/web/categories/'.$child.'/branch');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->client->request('GET', '/web/categories/'.$child.'/branch?include_deleted=1');
        self::assertResponseIsSuccessful();
        self::assertSame([$root, $child], array_column($this->responseData()['path'], 'id'));
        self::assertNotNull($this->responseData()['path'][1]['deleted_at']);
        $this->client->request('GET', '/web/categories/?parent_id='.$root.'&include_deleted=1');
        self::assertSame([$child], array_column($this->responseData()['categories'], 'id'));
        $this->client->request('GET', '/web/categories/?parent_id='.$child);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->client->request('GET', '/web/categories/?parent_id='.$child.'&include_deleted=1');
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->responseData()['categories']);
        $this->client->request('HEAD', '/web/categories/'.$child.'?include_deleted=1');
        self::assertResponseIsSuccessful();
        self::assertSame('', $this->client->getResponse()->getContent());

        foreach (['/', '/'.$root, '/'.$root.'/branch', '/?parent_id='.$root] as $suffix) {
            $this->client->request('GET', '/web/categories'.$suffix);
            self::assertResponseIsSuccessful();
            $expected = $this->responseData();

            $this->client->request('GET', '/web/categories'.$suffix.(str_contains($suffix, '?') ? '&' : '?').'include_deleted=invalid');
            self::assertResponseIsSuccessful();
            self::assertSame($expected, $this->responseData());

            $this->client->request('GET', '/web/categories'.$suffix.(str_contains($suffix, '?') ? '&' : '?').'include_deleted[]=1');
            self::assertResponseIsSuccessful();
            self::assertSame($expected, $this->responseData());
        }
    }

    public function testRestoresSubtreeAndNecessaryAncestorsButNotTheirOtherBranches(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory();
        $leaf = $this->createCategory();
        $sibling = $this->createCategory();
        $this->moveCategory($child, $root);
        $this->moveCategory($leaf, $child);
        $this->moveCategory($sibling, $root);
        $this->client->request('DELETE', '/web/categories/'.$leaf);
        $this->client->request('DELETE', '/web/categories/'.$root);
        $this->client->request('POST', '/web/categories/'.$child.'/restore');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertNull($this->responseData()['category']['deleted_at']);
        $this->assertParent($child, $root);
        $this->assertParent($root, null);
        $this->assertParent($leaf, $child);
        $this->client->request('GET', '/web/categories/'.$sibling);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->client->request('GET', '/web/categories/'.$sibling.'?include_deleted=1');
        self::assertNotNull($this->responseData()['category']['deleted_at']);
        $this->client->request('POST', '/web/categories/'.$child.'/restore');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testPermanentDeletionRemovesSubtreeLinksButPreservesProducts(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory();
        $this->moveCategory($child, $root);
        $this->client->jsonRequest('POST', '/web/products/', ['sku' => 'CATEGORY-TRASH-PRODUCT']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $product = $this->responseData()['product']['id'];
        $this->client->request('PUT', '/web/categories/'.$child.'/products/'.$product);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->client->request('DELETE', '/web/categories/'.$root);
        $this->client->request('GET', '/web/categories/'.$child.'/products/?include_deleted=1');
        self::assertResponseIsSuccessful();
        self::assertSame([$product], array_column($this->responseData()['products'], 'id'));
        $this->client->request('PUT', '/web/categories/'.$child.'/products/'.$product);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->client->request('DELETE', '/web/categories/'.$root.'/permanent');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM category'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_category'));
        $this->client->request('GET', '/web/products/'.$product);
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/web/categories/'.$root.'/restore');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->client->request('DELETE', '/web/categories/'.$root.'/permanent');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testLifecycleConflictsLeaveTheHierarchyUnchanged(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory();
        $this->moveCategory($child, $root);
        $this->client->request('DELETE', '/web/categories/'.$root.'/permanent');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->client->request('DELETE', '/web/categories/'.$root);
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $connection->executeStatement('UPDATE category SET deleted_at = NULL WHERE id = ?', [$child]);
        $this->client->request('DELETE', '/web/categories/'.$root.'/permanent');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM category'));
        $connection->executeStatement('UPDATE category SET parent_id = ? WHERE id = ?', [Uuid::v7()->toRfc4122(), $root]);
        $this->client->request('POST', '/web/categories/'.$root.'/restore');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertNotNull($connection->fetchOne('SELECT deleted_at FROM category WHERE id = ?', [$root]));
    }

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
        self::assertSame($id, $this->responseData()['category']['id']);
        self::assertSame($parentId, $this->responseData()['category']['parent_id']);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
    }

    private function assertParent(string $id, ?string $parentId): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->client->request('GET', '/web/categories/'.$id);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame($id, $this->responseData()['category']['id']);
        self::assertSame($parentId, $this->responseData()['category']['parent_id']);
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

    public static function invalidCreationPayloads(): iterable
    {
        yield 'invalid UUID' => ['{"parent_id":"invalid"}', Response::HTTP_UNPROCESSABLE_ENTITY];

        yield 'empty parent' => ['{"parent_id":""}', Response::HTTP_UNPROCESSABLE_ENTITY];

        yield 'integer parent' => ['{"parent_id":42}', Response::HTTP_UNPROCESSABLE_ENTITY];

        yield 'array parent' => ['{"parent_id":[]}', Response::HTTP_UNPROCESSABLE_ENTITY];

        yield 'malformed JSON' => ['{"parent_id":', Response::HTTP_BAD_REQUEST];
    }

    public static function failingOperations(): iterable
    {
        $path = '/web/categories/01994731-abcd-7000-8000-000000000000';

        yield 'list' => ['GET', '/web/categories/', 'findByParentId'];

        yield 'show' => ['GET', $path, 'findById'];

        yield 'branch' => ['GET', $path.'/branch', 'findBranch'];

        yield 'create' => ['POST', '/web/categories/', 'save'];

        yield 'move' => ['PATCH', $path, 'save'];

        yield 'delete' => ['DELETE', $path, 'delete'];

        yield 'PHP error' => ['GET', '/web/categories/', 'findByParentId', true];
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
