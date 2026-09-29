<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\CategoryProduct\ListCategoryProducts;

use App\Core\Catalog\Application\ReadModel\Projector\ProductProjector;
use App\Core\Catalog\Application\ReadModel\View\ProductView;
use App\Core\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Core\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Core\Catalog\Domain\Persistence\Repository\ProductCategoryRepositoryInterface;

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
