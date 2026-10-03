<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\GetCategory;

use App\Core\Catalog\Application\ReadModel\Projector\CategoryProjector;
use App\Core\Catalog\Application\ReadModel\View\CategoryView;
use App\Core\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Core\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;

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
