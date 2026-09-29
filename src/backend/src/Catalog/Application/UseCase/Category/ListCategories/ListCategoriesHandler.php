<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Category\ListCategories;

use App\Catalog\Application\ReadModel\Projector\CategoryProjector;
use App\Catalog\Application\ReadModel\View\CategoryView;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;

final readonly class ListCategoriesHandler
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
        private CategoryProjector $projector,
    ) {
    }

    /**
     * @return list<CategoryView>
     *
     * @throws CategoryNotFoundException
     */
    public function __invoke(ListCategoriesQuery $query): array
    {
        if (null !== $query->parentId && null === $this->categories->findById($query->parentId, $query->includeDeleted)) {
            throw new CategoryNotFoundException('Category not found.');
        }

        return $this->projector->project(
            $this->categories->findByParentId($query->parentId, $query->includeDeleted),
            $query->includeDeleted,
        );
    }
}
