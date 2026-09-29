<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Product\ListProducts;

final readonly class ListProductsQuery
{
    public function __construct(
        public bool $deleted = false,
    ) {
    }
}
