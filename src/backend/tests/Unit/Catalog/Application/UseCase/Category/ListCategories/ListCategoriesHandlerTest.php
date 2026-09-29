<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\UseCase\Category\ListCategories;

use App\Catalog\Application\ReadModel\Projector\CategoryProjector;
use App\Catalog\Application\ReadModel\View\CategoryView;
use App\Catalog\Application\UseCase\Category\ListCategories\ListCategoriesHandler;
use App\Catalog\Application\UseCase\Category\ListCategories\ListCategoriesQuery;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListCategoriesHandler::class)]
#[UsesClass(ListCategoriesQuery::class)]
#[UsesClass(CategoryProjector::class)]
#[UsesClass(CategoryView::class)]
#[UsesClass(Category::class)]
#[UsesClass(Id::class)]
#[UsesClass(CategoryNotFoundException::class)]
final class ListCategoriesHandlerTest extends TestCase
{
    // ========================================================================
    // Roots: omitted parent lists top-level categories without a parent lookup
    // ========================================================================

    public function testListsRoots(): void
    {
        $root = new Category(Id::fromString('01994731-abcd-7000-8000-000000000001'));
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects(self::never())->method('findById');
        $repository->expects(self::once())->method('findByParentId')->with(null, false)->willReturn([$root]);
        $repository->expects(self::once())->method('findParentIdsWithChildren')->with([$root->id], false)->willReturn([]);

        $result = (new ListCategoriesHandler($repository, new CategoryProjector($repository)))(new ListCategoriesQuery());

        self::assertSame($root->id->toString(), $result[0]->id);
    }

    // ========================================================================
    // Children: the requested parent must exist in the selected visibility mode
    // ========================================================================

    public function testListsChildrenOfAnExistingParent(): void
    {
        $parent = new Category(Id::fromString('01994731-abcd-7000-8000-000000000001'));
        $child = new Category(Id::fromString('01994731-abcd-7000-8000-000000000002'), $parent->id);
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects(self::once())->method('findById')->with($parent->id, true)->willReturn($parent);
        $repository->expects(self::once())->method('findByParentId')->with($parent->id, true)->willReturn([$child]);
        $repository->expects(self::once())->method('findParentIdsWithChildren')->with([$child->id], true)->willReturn([]);

        $result = (new ListCategoriesHandler($repository, new CategoryProjector($repository)))(new ListCategoriesQuery($parent->id, true));

        self::assertSame($parent->id->toString(), $result[0]->parentId);
    }

    public function testRejectsMissingParentWithoutListingChildren(): void
    {
        $id = Id::fromString('01994731-abcd-7000-8000-000000000001');
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects(self::once())->method('findById')->with($id, false)->willReturn(null);
        $repository->expects(self::never())->method('findByParentId');
        $this->expectException(CategoryNotFoundException::class);

        (new ListCategoriesHandler($repository, new CategoryProjector($repository)))(new ListCategoriesQuery($id));
    }
}
