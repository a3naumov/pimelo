<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\CategoryProduct\AttachProductToCategory;

use App\Shared\General\Identity\Id;

final readonly class AttachProductToCategoryCommand
{
    public function __construct(
        public Id $productId,
        public Id $categoryId,
    ) {
    }
}
