<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Product\CreateProduct;

final readonly class CreateProductCommand
{
    public function __construct(
        public string $sku,
    ) {
    }
}
