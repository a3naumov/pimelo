<?php

declare(strict_types=1);

namespace App\Web\Catalog\Entity;

use App\Web\General\Identity\Id;

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
