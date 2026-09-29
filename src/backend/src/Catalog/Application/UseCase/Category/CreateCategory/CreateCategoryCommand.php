<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Category\CreateCategory;

use App\General\Identity\Id;

final readonly class CreateCategoryCommand
{
    public function __construct(
        public ?Id $parentId = null,
    ) {
    }
}
