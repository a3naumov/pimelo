<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Product\UpdateProduct;

use App\General\Identity\Id;

final readonly class UpdateProductCommand
{
    public function __construct(
        public Id $id,
        public string $sku,
    ) {
    }
}
