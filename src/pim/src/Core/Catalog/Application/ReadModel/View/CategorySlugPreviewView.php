<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\ReadModel\View;

final readonly class CategorySlugPreviewView
{
    public function __construct(
        public string $slug,
        public bool $available,
        public string $suggestedSlug,
    ) {
    }
}
