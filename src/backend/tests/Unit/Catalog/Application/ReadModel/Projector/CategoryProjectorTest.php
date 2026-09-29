<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\ReadModel\Projector;

use App\Catalog\Application\ReadModel\Projector\CategoryProjector;
use App\Catalog\Application\ReadModel\View\CategoryView;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CategoryProjector::class)]
#[UsesClass(CategoryView::class)]
#[UsesClass(Category::class)]
#[UsesClass(Id::class)]
final class CategoryProjectorTest extends TestCase
{
    // ========================================================================
    // Projection: child flags use the requested category visibility
    // ========================================================================

    public function testProjectsChildrenForAllCategoriesInOneLookup(): void
    {
        $first = new Category(Id::fromString('01994731-abcd-7000-8000-000000000001'));
        $second = new Category(Id::fromString('01994731-abcd-7000-8000-000000000002'));
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects(self::once())->method('findParentIdsWithChildren')
            ->with([$first->id, $second->id], true)
            ->willReturn([$second->id->toString()]);

        $views = (new CategoryProjector($repository))->project([$first, $second], true);

        self::assertSame($first->id->toString(), $views[0]->id);
        self::assertNull($views[0]->parentId);
        self::assertFalse($views[0]->hasChildren);
        self::assertSame($second->id->toString(), $views[1]->id);
        self::assertNull($views[1]->parentId);
        self::assertTrue($views[1]->hasChildren);
        self::assertSame(['id', 'parentId', 'hasChildren', 'deletedAt'], array_keys(get_object_vars($views[0])));
    }
}
