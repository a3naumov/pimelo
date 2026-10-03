<?php

declare(strict_types=1);

namespace App\Core\Catalog\Domain\Entity;

use App\Shared\General\Identity\Id;

final class Product
{
    public function __construct(
        public private(set) Id $id {
            get => $this->id;
        },
        public private(set) string $sku {
            get => $this->sku;
        },
        public private(set) ?\DateTimeImmutable $deletedAt = null {
            get => $this->deletedAt;
        },
    ) {
    }
}
