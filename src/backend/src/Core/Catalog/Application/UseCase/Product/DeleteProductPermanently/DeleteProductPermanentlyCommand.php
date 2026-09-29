<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Product\DeleteProductPermanently;

use App\Shared\General\Identity\Id;

final readonly class DeleteProductPermanentlyCommand
{
    public function __construct(
        public Id $id,
    ) {
    }
}
