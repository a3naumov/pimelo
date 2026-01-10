<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\Mapper\Product;

use Pimelo\Core\Catalog\Application\Dto\Product\ProductDto;
use Pimelo\Core\Catalog\Domain\Entity\Product;

class ProductMapper
{
    public function fromDomain(Product $product): ProductDto
    {
        return new ProductDto(
            id: $product->getId()->toString(),
            storeId: $product->getStoreId()->toString(),
        );
    }
}
