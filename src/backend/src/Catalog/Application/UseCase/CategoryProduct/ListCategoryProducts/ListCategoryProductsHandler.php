<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\CategoryProduct\ListCategoryProducts;

use App\Catalog\Application\ReadModel\Projector\ProductProjector;
use App\Catalog\Application\ReadModel\View\ProductView;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Persistence\Repository\ProductCategoryRepositoryInterface;

final readonly class ListCategoryProductsHandler
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
        private ProductCategoryRepositoryInterface $links,
        private ProductProjector $projector,
    ) {
    }

    /**
     * @return list<ProductView>
     *
     * @throws CategoryNotFoundException
     */
    public function __invoke(ListCategoryProductsQuery $query): array
    {
        $category = $this->categories->findById($query->categoryId, $query->includeDeleted);

        if (null === $category) {
            throw new CategoryNotFoundException('Category not found.');
        }

        return $this->projector->many($this->links->findProducts($category));
    }
}
