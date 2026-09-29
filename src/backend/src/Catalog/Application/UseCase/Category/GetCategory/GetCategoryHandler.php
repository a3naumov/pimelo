<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Category\GetCategory;

use App\Catalog\Application\ReadModel\Projector\CategoryProjector;
use App\Catalog\Application\ReadModel\View\CategoryView;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;

final readonly class GetCategoryHandler
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
        private CategoryProjector $projector,
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     */
    public function __invoke(GetCategoryQuery $query): CategoryView
    {
        $category = $this->categories->findById($query->id, $query->includeDeleted);

        if (null === $category) {
            throw new CategoryNotFoundException('Category not found.');
        }

        return $this->projector->one($category, $query->includeDeleted);
    }
}
