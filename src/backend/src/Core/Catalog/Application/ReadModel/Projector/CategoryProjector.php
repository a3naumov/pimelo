<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\ReadModel\Projector;

use App\Core\Catalog\Application\ReadModel\View\CategoryView;
use App\Core\Catalog\Domain\Entity\Category;
use App\Core\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Shared\General\Identity\Id;

final readonly class CategoryProjector
{
    public function __construct(private CategoryRepositoryInterface $categories)
    {
    }

    /**
     * @param list<Category> $categories
     *
     * @return list<CategoryView>
     */
    public function project(array $categories, bool $includeDeleted = false): array
    {
        $parents = $this->categories->findParentIdsWithChildren(
            array_map(static fn (Category $category): Id => $category->id, $categories),
            $includeDeleted,
        );

        return array_map(static fn (Category $category): CategoryView => new CategoryView(
            $category->id->toString(),
            $category->parentId?->toString(),
            in_array($category->id->toString(), $parents, true),
            $category->deletedAt,
        ), $categories);
    }

    public function one(Category $category, bool $includeDeleted = false): CategoryView
    {
        return $this->project([$category], $includeDeleted)[0];
    }
}
