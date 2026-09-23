<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\UseCase\Category\MoveCategory;

use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryCommand;
use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryHandler;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Domain\Hierarchy\CategoryAncestryInterface;
use App\Catalog\Domain\Hierarchy\CategoryAncestryResult;
use App\Catalog\Domain\Hierarchy\CategoryHierarchyTransactionInterface;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Service\Category\CategoryMover;
use App\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(MoveCategoryHandler::class)]
#[UsesClass(MoveCategoryCommand::class)]
#[UsesClass(Category::class)]
#[UsesClass(CategoryMover::class)]
#[UsesClass(CategoryAncestryResult::class)]
#[UsesClass(Id::class)]
#[UsesClass(CategoryNotFoundException::class)]
#[UsesClass(InvalidCategoryHierarchyException::class)]
final class MoveCategoryHandlerTest extends TestCase
{
    private CategoryRepositoryInterface&MockObject $repository;
    private CategoryAncestryInterface&MockObject $ancestry;
    private MoveCategoryHandler $handler;
    private bool $inTransaction = false;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CategoryRepositoryInterface::class);
        $this->ancestry = $this->createMock(CategoryAncestryInterface::class);
        $transaction = $this->createMock(CategoryHierarchyTransactionInterface::class);
        $transaction->expects(self::once())->method('run')->willReturnCallback(function (callable $operation): mixed {
            $this->inTransaction = true;
            try {
                return $operation();
            } finally {
                $this->inTransaction = false;
            }
        });
        $this->handler = new MoveCategoryHandler($this->repository, new CategoryMover($this->ancestry), $transaction);
    }

    // ========================================================================
    // Orchestration: reads, checks and writes stay inside the transaction
    // ========================================================================

    public function testMovesCategoryInsideTransaction(): void
    {
        $category = $this->category('1');
        $parent = $this->category('2');
        $this->repository->expects(self::exactly(2))->method('findById')->willReturnCallback(function (Id $id) use ($category, $parent): Category {
            self::assertTrue($this->inTransaction);

            return $id->equals($category->getId()) ? $category : $parent;
        });
        $this->ancestry->expects(self::once())->method('inspect')->willReturnCallback(function () use ($category): CategoryAncestryResult {
            self::assertTrue($this->inTransaction);
            self::assertNull($category->getParentId());

            return new CategoryAncestryResult(false, false);
        });
        $this->repository->expects(self::once())->method('save')->willReturnCallback(function (Category $moved) use ($parent): Category {
            self::assertTrue($this->inTransaction);
            self::assertEquals($parent->getId(), $moved->getParentId());

            return $moved;
        });

        $moved = ($this->handler)(new MoveCategoryCommand($category->getId(), $parent->getId()));

        self::assertEquals($parent->getId(), $moved->getParentId());
        self::assertFalse($this->inTransaction);
    }

    public function testMoveToRootDoesNotLoadParentOrAncestry(): void
    {
        $category = $this->category('1')->moveTo($this->category('2')->getId());
        $this->repository->expects(self::once())->method('findById')->with($category->getId())->willReturn($category);
        $this->ancestry->expects(self::never())->method('inspect');
        $this->repository->expects(self::once())->method('save')->willReturnArgument(0);

        self::assertNull(($this->handler)(new MoveCategoryCommand($category->getId(), null))->getParentId());
    }

    // ========================================================================
    // Rejections: missing records and invalid ancestry never reach persistence
    // ========================================================================

    #[DataProvider('missingRecords')]
    public function testMissingRecordsAreNotSaved(bool $missingCategory, string $message): void
    {
        $category = $this->category('1');
        $this->repository->expects(self::exactly($missingCategory ? 1 : 2))->method('findById')
            ->willReturnOnConsecutiveCalls(...($missingCategory ? [null] : [$category, null]));
        $this->repository->expects(self::never())->method('save');
        $this->ancestry->expects(self::never())->method('inspect');
        $this->expectException(CategoryNotFoundException::class);
        $this->expectExceptionMessage($message);

        ($this->handler)(new MoveCategoryCommand($category->getId(), $this->category('2')->getId()));
    }

    public function testCycleIsNotSaved(): void
    {
        $category = $this->category('1');
        $parent = $this->category('2');
        $this->repository->expects(self::exactly(2))->method('findById')->willReturnOnConsecutiveCalls($category, $parent);
        $this->ancestry->expects(self::once())->method('inspect')->willReturn(new CategoryAncestryResult(true, false));
        $this->repository->expects(self::never())->method('save');
        $this->expectException(InvalidCategoryHierarchyException::class);

        ($this->handler)(new MoveCategoryCommand($category->getId(), $parent->getId()));
    }

    private function category(string $suffix): Category
    {
        return new Category(Id::fromString('01994731-abcd-7000-8000-00000000000'.$suffix));
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function missingRecords(): iterable
    {
        yield 'missing category' => [true, 'Category not found.'];
        yield 'missing parent' => [false, 'Parent category not found.'];
    }
}
