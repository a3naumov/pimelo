<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\CategoryProduct\AttachProductToCategory;

use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Exception\Product\ProductNotFoundException;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Persistence\Repository\ProductCategoryRepositoryInterface;
use App\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;

final readonly class AttachProductToCategoryHandler
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private CategoryRepositoryInterface $categories,
        private ProductCategoryRepositoryInterface $links,
    ) {
    }

    /**
     * @throws ProductNotFoundException
     * @throws CategoryNotFoundException
     */
    public function __invoke(AttachProductToCategoryCommand $command): void
    {
        $product = $this->products->findById($command->productId);

        if (null === $product) {
            throw new ProductNotFoundException('Product not found.');
        }

        $category = $this->categories->findById($command->categoryId);

        if (null === $category) {
            throw new CategoryNotFoundException('Category not found.');
        }

        $this->links->attach($product, $category);
    }
}
