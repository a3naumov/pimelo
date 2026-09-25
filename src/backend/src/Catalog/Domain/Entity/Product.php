<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\General\Identity\Id;

final class Product
{
    public function __construct(
        public private(set) Id $id {
            get => $this->id;
        },
        public private(set) string $sku {
            get => $this->sku;
        },
    ) {
    }
}
