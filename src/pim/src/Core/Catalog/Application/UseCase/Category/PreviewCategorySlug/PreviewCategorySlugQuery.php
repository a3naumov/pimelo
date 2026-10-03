<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\PreviewCategorySlug;

use App\Shared\General\Identity\Id;

final readonly class PreviewCategorySlugQuery
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?Id $excludeId = null,
    ) {
    }
}
