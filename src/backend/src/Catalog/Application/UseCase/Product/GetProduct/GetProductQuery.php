<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Product\GetProduct;

use App\General\Identity\Id;

final readonly class GetProductQuery
{
    public function __construct(
        public Id $id,
        public bool $includeDeleted = false,
    ) {
    }
}
