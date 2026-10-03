<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\UpdateCategory;

use App\Shared\General\Identity\Id;

final readonly class UpdateCategoryCommand
{
    public function __construct(
        public Id $categoryId,
        public ?Id $parentId,
        public ?string $name = null,
        public ?string $slug = null,
        public bool $allowSlugSuffix = false,
    ) {
    }
}
