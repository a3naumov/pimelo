<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Service\Category;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Domain\Hierarchy\CategoryAncestryInterface;
use App\Catalog\Domain\Hierarchy\CategoryAncestryResult;
use App\Catalog\Domain\Service\Category\CategoryMover;
use App\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CategoryMover::class)]
#[UsesClass(Category::class)]
#[UsesClass(CategoryAncestryResult::class)]
#[UsesClass(Id::class)]
#[UsesClass(InvalidCategoryHierarchyException::class)]
final class CategoryMoverTest extends TestCase
{
    // ========================================================================
    // Movement: domain decisions use facts, not persistence details
    // ========================================================================

    public function testMoveToUnrelatedParent(): void
    {
        $category = $this->category('1');
        $parent = $this->category('2');
        $ancestry = $this->createMock(CategoryAncestryInterface::class);
        $ancestry->expects(self::once())->method('inspect')
            ->with($category->getId(), $parent->getId())
            ->willReturn(new CategoryAncestryResult(false, false));

        $moved = new CategoryMover($ancestry)->move($category, $parent);

        self::assertEquals($parent->getId(), $moved->getParentId());
        self::assertNull($category->getParentId());
    }

    public function testMoveToRootDoesNotReadAncestry(): void
    {
        $category = $this->category('1')->moveTo($this->category('2')->getId());
        $ancestry = $this->createMock(CategoryAncestryInterface::class);
        $ancestry->expects(self::never())->method('inspect');

        $moved = new CategoryMover($ancestry)->move($category, null);

        self::assertNull($moved->getParentId());
        self::assertNotNull($category->getParentId());
    }

    public function testSelfParentIsRejectedWithoutReadingAncestry(): void
    {
        $category = $this->category('1');
        $ancestry = $this->createMock(CategoryAncestryInterface::class);
        $ancestry->expects(self::never())->method('inspect');
        $this->expectException(InvalidCategoryHierarchyException::class);

        new CategoryMover($ancestry)->move($category, $category);
    }

    #[DataProvider('invalidAncestry')]
    public function testInvalidAncestryIsRejected(bool $isAncestor, bool $hasCycle): void
    {
        $category = $this->category('1');
        $ancestry = $this->createStub(CategoryAncestryInterface::class);
        $ancestry->method('inspect')->willReturn(new CategoryAncestryResult($isAncestor, $hasCycle));

        try {
            new CategoryMover($ancestry)->move($category, $this->category('2'));
            self::fail('Invalid ancestry must be rejected.');
        } catch (InvalidCategoryHierarchyException $exception) {
            self::assertSame('Moving this category would create a cycle.', $exception->getMessage());
            self::assertNull($category->getParentId());
        }
    }

    private function category(string $suffix): Category
    {
        return new Category(Id::fromString('01994731-abcd-7000-8000-00000000000'.$suffix));
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function invalidAncestry(): iterable
    {
        yield 'descendant parent' => [true, false];
        yield 'pre-existing cycle in parent ancestry' => [false, true];
        yield 'both facts present' => [true, true];
    }
}
