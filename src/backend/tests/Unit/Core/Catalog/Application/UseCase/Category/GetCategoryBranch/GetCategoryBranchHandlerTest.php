<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Catalog\Application\UseCase\Category\GetCategoryBranch;

use App\Core\Catalog\Application\ReadModel\Projector\CategoryProjector;
use App\Core\Catalog\Application\ReadModel\View\CategoryBranchView;
use App\Core\Catalog\Application\ReadModel\View\CategoryView;
use App\Core\Catalog\Application\UseCase\Category\GetCategoryBranch\GetCategoryBranchHandler;
use App\Core\Catalog\Application\UseCase\Category\GetCategoryBranch\GetCategoryBranchQuery;
use App\Core\Catalog\Domain\Entity\Category;
use App\Core\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Core\Catalog\Domain\Hierarchy\CategoryBranch;
use App\Core\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Shared\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetCategoryBranchHandler::class)]
#[UsesClass(GetCategoryBranchQuery::class)]
#[UsesClass(CategoryProjector::class)]
#[UsesClass(CategoryBranchView::class)]
#[UsesClass(CategoryView::class)]
#[UsesClass(CategoryBranch::class)]
#[UsesClass(Category::class)]
#[UsesClass(Id::class)]
#[UsesClass(CategoryNotFoundException::class)]
final class GetCategoryBranchHandlerTest extends TestCase
{
    // ========================================================================
    // Branch: the path and levels share the same category projections
    // ========================================================================

    public function testReturnsProjectedPathAndSiblingLevels(): void
    {
        $root = new Category(Id::fromString('01994731-abcd-7000-8000-000000000001'), 'Category', Id::fromString('01994731-abcd-7000-8000-000000000001')->toString());
        $child = new Category(Id::fromString('01994731-abcd-7000-8000-000000000002'), 'Category', Id::fromString('01994731-abcd-7000-8000-000000000002')->toString(), $root->id);
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects(self::once())->method('findBranch')->with($child->id, true)
            ->willReturn(new CategoryBranch(
                [$root, $child],
                [
                    ['parentId' => null, 'categories' => [$root]],
                    ['parentId' => $root->id, 'categories' => [$child]],
                ],
            ));
        $repository->expects(self::once())->method('findParentIdsWithChildren')->with([$root->id, $child->id], true)
            ->willReturn([$root->id->toString()]);

        $result = (new GetCategoryBranchHandler($repository, new CategoryProjector($repository)))(new GetCategoryBranchQuery($child->id, true));

        self::assertSame($root->id->toString(), $result->path[0]->id);
        self::assertTrue($result->path[0]->hasChildren);
        self::assertSame($child->id->toString(), $result->path[1]->id);
        self::assertSame($root->id->toString(), $result->levels[1]['parentId']);
        self::assertSame($result->path[1], $result->levels[1]['categories'][0]);
    }

    // ========================================================================
    // Missing branch: absent categories do not leak into presentation
    // ========================================================================

    public function testMissingBranchThrowsNotFound(): void
    {
        $id = Id::fromString('01994731-abcd-7000-8000-000000000001');
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('findBranch')->willReturn(null);
        $this->expectException(CategoryNotFoundException::class);

        (new GetCategoryBranchHandler($repository, new CategoryProjector($repository)))(new GetCategoryBranchQuery($id));
    }
}
