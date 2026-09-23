<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Persistence\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;

interface ProductCategoryRepositoryInterface
{
    /** @return iterable<Category> */
    public function findCategories(Product $product): iterable;

    public function attach(Product $product, Category $category): void;

    public function detach(Product $product, Category $category): void;
}
