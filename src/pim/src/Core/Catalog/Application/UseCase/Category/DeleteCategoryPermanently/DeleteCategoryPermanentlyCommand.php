<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\DeleteCategoryPermanently;

use App\Shared\General\Identity\Id;

final readonly class DeleteCategoryPermanentlyCommand
{
    public function __construct(
        public Id $id,
    ) {
    }
}
