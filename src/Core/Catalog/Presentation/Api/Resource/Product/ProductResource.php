<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Presentation\Api\Resource\Product;

use Pimelo\Core\Catalog\Domain\Entity\Product;

final readonly class ProductResource implements \JsonSerializable
{
    public function __construct(private Product $product)
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
            'id' => $this->product->getId()->toString(),
            'store_id' => $this->product->getStoreId()->toString(),
        ];
    }
}
