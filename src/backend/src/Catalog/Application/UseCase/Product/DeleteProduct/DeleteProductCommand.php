<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Product\DeleteProduct;

use App\General\Identity\Id;

final readonly class DeleteProductCommand
{
    public function __construct(
        public Id $id,
    ) {
    }
}
