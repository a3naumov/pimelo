<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Product\RestoreProduct;

use App\General\Identity\Id;

final readonly class RestoreProductCommand
{
    public function __construct(
        public Id $id,
    ) {
    }
}
