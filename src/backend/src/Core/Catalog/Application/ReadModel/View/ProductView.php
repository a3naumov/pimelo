<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\ReadModel\View;

final readonly class ProductView
{
    public function __construct(
        public string $id,
        public string $sku,
        public ?\DateTimeImmutable $deletedAt,
    ) {
    }
}
