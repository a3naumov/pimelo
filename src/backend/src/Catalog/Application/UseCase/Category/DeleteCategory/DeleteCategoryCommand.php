<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Category\DeleteCategory;

use App\General\Identity\Id;

final readonly class DeleteCategoryCommand
{
    public function __construct(
        public Id $id,
    ) {
    }
}
