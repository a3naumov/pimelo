<?php

declare(strict_types=1);

namespace App\Web\Catalog\Http\Api\Resource;

use JsonSerializable;

final readonly class Product implements JsonSerializable
{
    public function __construct(
        private string $id,
        private string $sku,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
        ];
    }
}
