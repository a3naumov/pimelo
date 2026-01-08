<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use Pimelo\Core\Catalog\Domain\Entity\Product as DomainProduct;
use Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product;
use Pimelo\Shared\Identity\ID;

class ProductMapper
{
    public function fromDomain(DomainProduct $product): Product
    {
        return new Product(
            id: $product->getId()->toString(),
            storeId: $product->getStoreId()->toString(),
        );
    }

    public function toDomain(Product $doctrineProduct): DomainProduct
    {
        return new DomainProduct(
            id: ID::fromString($doctrineProduct->getId()->toRfc4122()),
            storeId: ID::fromString($doctrineProduct->getStoreId()->toRfc4122()),
        );
    }
}
