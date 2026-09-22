<?php

declare(strict_types=1);

namespace App\Catalog\Persistence\Repository;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Product;

interface ProductCategoryRepositoryInterface
{
    /** @return iterable<Category> */
    public function findCategories(Product $product): iterable;

    public function attach(Product $product, Category $category): void;

    public function detach(Product $product, Category $category): void;
}
