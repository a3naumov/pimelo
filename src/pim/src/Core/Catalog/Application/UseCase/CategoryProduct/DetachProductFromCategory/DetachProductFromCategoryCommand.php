<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\CategoryProduct\DetachProductFromCategory;

use App\Shared\General\Identity\Id;

final readonly class DetachProductFromCategoryCommand
{
    public function __construct(
        public Id $productId,
        public Id $categoryId,
    ) {
    }
}
