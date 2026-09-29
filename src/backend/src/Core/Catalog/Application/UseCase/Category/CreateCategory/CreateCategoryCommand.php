<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\CreateCategory;

use App\Shared\General\Identity\Id;

final readonly class CreateCategoryCommand
{
    public function __construct(
        public ?Id $parentId = null,
    ) {
    }
}
