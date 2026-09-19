<?php

declare(strict_types=1);

namespace App\Web\Catalog\Adapter\Symfony\Persistence\Doctrine\Mapper;

use App\Web\Catalog\Adapter\Symfony\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Web\Catalog\Entity\Product;
use App\Web\General\Identity\Id;
use Symfony\Component\Uid\Uuid;

final readonly class ProductMapper
{
    public function fromDoctrine(DoctrineProduct $doctrineProduct): Product
    {
        return new Product(
            id: Id::fromString($doctrineProduct->getId()->toRfc4122()),
            sku: $doctrineProduct->getSku(),
        );
    }

    public function toDoctrine(Product $product, ?DoctrineProduct $doctrineProduct = null): DoctrineProduct
    {
        if (null === $doctrineProduct) {
            return new DoctrineProduct(
                id: Uuid::fromString($product->getId()->toString()),
                sku: $product->getSku(),
            );
        }

        $doctrineProduct->setSku($product->getSku());

        return $doctrineProduct;
    }
}
