<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Product\RestoreProduct;

use App\Shared\General\Identity\Id;

final readonly class RestoreProductCommand
{
    public function __construct(
        public Id $id,
    ) {
    }
}
