<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Product\DeleteProduct;

use App\Shared\General\Identity\Id;

final readonly class DeleteProductCommand
{
    public function __construct(
        public Id $id,
    ) {
    }
}
