<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use App\Catalog\Domain\Entity\Product;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\General\Identity\Id;
use Symfony\Component\Uid\Uuid;

final readonly class ProductMapper
{
    public function fromDoctrine(DoctrineProduct $doctrineProduct): Product
    {
        return new Product(
            id: Id::fromString($doctrineProduct->id->toRfc4122()),
            sku: $doctrineProduct->sku,
        );
    }

    /** @throws \InvalidArgumentException */
    public function toDoctrine(Product $product, ?DoctrineProduct $doctrineProduct = null): DoctrineProduct
    {
        if (null === $doctrineProduct) {
            return new DoctrineProduct(
                id: Uuid::fromString($product->id->toString()),
                sku: $product->sku,
            );
        }

        $doctrineProduct->sku = $product->sku;

        return $doctrineProduct;
    }
}
