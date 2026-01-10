<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Presentation\Api\Resource\Product;

use Pimelo\Core\Catalog\Application\Dto\Product\ProductDto;

class ProductResource implements \JsonSerializable
{
    public function __construct(private readonly ProductDto $product)
    {
    }

    /**
     * @return array{
     *     id: string,
     *     store_id: string,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->product->getId(),
            'store_id' => $this->product->getStoreId(),
        ];
    }
}
