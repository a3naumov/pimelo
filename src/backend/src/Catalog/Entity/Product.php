<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use App\General\Identity\Id;

final readonly class Product
{
    public function __construct(
        private Id $id,
        private string $sku,
    ) {
    }

    public function getId(): Id
    {
        return $this->id;
    }

    public function getSku(): string
    {
        return $this->sku;
    }
}
