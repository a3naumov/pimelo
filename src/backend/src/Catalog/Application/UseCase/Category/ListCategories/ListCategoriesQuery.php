<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Category\ListCategories;

use App\General\Identity\Id;

final readonly class ListCategoriesQuery
{
    public function __construct(
        public ?Id $parentId = null,
        public bool $includeDeleted = false,
    ) {
    }
}
