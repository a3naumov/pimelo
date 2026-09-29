<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\CategoryProduct\ListCategoryProducts;

use App\Shared\General\Identity\Id;

final readonly class ListCategoryProductsQuery
{
    public function __construct(
        public Id $categoryId,
        public bool $includeDeleted = false,
    ) {
    }
}
