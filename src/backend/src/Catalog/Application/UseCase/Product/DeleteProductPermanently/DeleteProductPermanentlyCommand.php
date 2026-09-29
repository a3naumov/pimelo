<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Product\DeleteProductPermanently;

use App\General\Identity\Id;

final readonly class DeleteProductPermanentlyCommand
{
    public function __construct(
        public Id $id,
    ) {
    }
}
