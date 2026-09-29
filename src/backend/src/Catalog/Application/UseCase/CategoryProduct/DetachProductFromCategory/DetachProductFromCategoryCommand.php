<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\CategoryProduct\DetachProductFromCategory;

use App\General\Identity\Id;

final readonly class DetachProductFromCategoryCommand
{
    public function __construct(
        public Id $productId,
        public Id $categoryId,
    ) {
    }
}
