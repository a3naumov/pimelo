<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\ListCategories;

use App\Core\Catalog\Application\ReadModel\Projector\CategoryProjector;
use App\Core\Catalog\Application\ReadModel\View\CategoryView;
use App\Core\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Core\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;

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
