<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\CategoryProduct\ListCategoryProducts;

use App\General\Identity\Id;

final readonly class ListCategoryProductsQuery
{
    public function __construct(
        public Id $categoryId,
        public bool $includeDeleted = false,
    ) {
    }
}
