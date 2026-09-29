<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\CategoryProduct\AttachProductToCategory;

use App\General\Identity\Id;

final readonly class AttachProductToCategoryCommand
{
    public function __construct(
        public Id $productId,
        public Id $categoryId,
    ) {
    }
}
