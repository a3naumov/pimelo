<?php

declare(strict_types=1);

namespace App\Core\Catalog\Domain\Persistence\Repository;

use App\Core\Catalog\Domain\Entity\Category;
use App\Core\Catalog\Domain\Entity\Product;

interface ProductCategoryRepositoryInterface
{
    /**
     * @return iterable<Category>
     */
    public function findCategories(Product $product): iterable;

    /**
     * @return list<Product>
     */
    public function findProducts(Category $category): array;

    public function attach(Product $product, Category $category): void;

    public function detach(Product $product, Category $category): void;
}
