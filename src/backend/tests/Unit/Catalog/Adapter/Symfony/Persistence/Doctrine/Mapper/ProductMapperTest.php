<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Adapter\Symfony\Persistence\Doctrine\Mapper;

use App\Catalog\Adapter\Symfony\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Catalog\Adapter\Symfony\Persistence\Doctrine\Mapper\ProductMapper;
use App\Catalog\Entity\Product;
use App\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(ProductMapper::class)]
#[UsesClass(DoctrineProduct::class)]
#[UsesClass(Product::class)]
#[UsesClass(Id::class)]
final class ProductMapperTest extends TestCase
{
    // ========================================================================
    // From Doctrine: preserves identity and fields in the domain model
    // ========================================================================

    public function testFromDoctrinePreservesIdentityAndSku(): void
    {
        $id = Uuid::fromString('01994731-0123-7000-8000-000000000000');
        $doctrineProduct = new DoctrineProduct(id: $id, sku: 'product-1');

        $product = new ProductMapper()->fromDoctrine($doctrineProduct);

        self::assertSame($id->toRfc4122(), $product->getId()->toString());
        self::assertSame('product-1', $product->getSku());
    }

    // ========================================================================
    // To Doctrine: creates a new entity from the domain model
    // ========================================================================

    public function testToDoctrineCreatesEntityWithIdentityAndSku(): void
    {
        $id = Id::fromString('01994731-0123-7000-8000-000000000000');
        $product = new Product(id: $id, sku: 'product-1');

        $doctrineProduct = new ProductMapper()->toDoctrine($product);

        self::assertSame($id->toString(), $doctrineProduct->getId()->toRfc4122());
        self::assertSame('product-1', $doctrineProduct->getSku());
    }

    // ========================================================================
    // To Doctrine: updates the existing entity without replacing it or its ID
    // ========================================================================

    public function testToDoctrineUpdatesExistingEntityInPlace(): void
    {
        $id = Uuid::fromString('01994731-0123-7000-8000-000000000000');
        $doctrineProduct = new DoctrineProduct(id: $id, sku: 'original-sku');
        $product = new Product(id: Id::fromString($id->toRfc4122()), sku: 'updated-sku');

        $updated = new ProductMapper()->toDoctrine($product, $doctrineProduct);

        self::assertSame($doctrineProduct, $updated);
        self::assertSame($id, $updated->getId());
        self::assertSame('updated-sku', $updated->getSku());
    }
}
