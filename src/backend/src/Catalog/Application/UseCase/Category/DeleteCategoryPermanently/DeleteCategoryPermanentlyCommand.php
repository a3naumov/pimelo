<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Category\DeleteCategoryPermanently;

use App\General\Identity\Id;

final readonly class DeleteCategoryPermanentlyCommand
{
    public function __construct(
        public Id $id,
    ) {
    }
}
