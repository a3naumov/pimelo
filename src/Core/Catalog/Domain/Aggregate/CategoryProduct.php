<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Domain\Aggregate;

use Pimelo\Core\Catalog\Domain\Entity\Category;
use Pimelo\Core\Catalog\Domain\Entity\Product;
use Pimelo\Shared\Identity\ID;

class CategoryProduct
{
    public function __construct(
        private readonly ID $categoryId,
        private readonly ID $productId,
    ) {
    }

    public static function assign(Category $category, Product $product): self
    {
        return new self($category->getId(), $product->getId());
    }

    public function getProductId(): ID
    {
        return $this->productId;
    }

    public function getCategoryId(): ID
    {
        return $this->categoryId;
    }
}
