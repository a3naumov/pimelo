<?php

declare(strict_types=1);

namespace App\Catalog\Application\ReadModel\View;

final readonly class CategoryView
{
    public function __construct(
        public string $id,
        public ?string $parentId,
        public bool $hasChildren,
        public ?\DateTimeImmutable $deletedAt,
    ) {
    }
}
