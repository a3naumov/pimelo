<?php

declare(strict_types=1);

namespace App\Web\Catalog\Http\Api\Resource;

final readonly class Product implements \JsonSerializable
{
    public function __construct(
        private string $id,
        private string $sku,
    ) {
    }

    /** @return array{id: string, sku: string} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
        ];
    }
}
