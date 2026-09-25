<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use App\Catalog\Domain\Entity\Product;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\ProductMapper;
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

        self::assertSame($id->toRfc4122(), $product->id->toString());
        self::assertSame('product-1', $product->sku);
    }

    // ========================================================================
    // To Doctrine: creates a new entity from the domain model
    // ========================================================================

    public function testToDoctrineCreatesEntityWithIdentityAndSku(): void
    {
        $id = Id::fromString('01994731-0123-7000-8000-000000000000');
        $product = new Product(id: $id, sku: 'product-1');

        $doctrineProduct = new ProductMapper()->toDoctrine($product);

        self::assertSame($id->toString(), $doctrineProduct->id->toRfc4122());
        self::assertSame('product-1', $doctrineProduct->sku);
        self::assertNull($doctrineProduct->deletedAt);
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
        self::assertSame($id, $updated->id);
        self::assertSame('updated-sku', $updated->sku);
    }

    // ========================================================================
    // Soft deletion: mapping never restores an archived persistence entity
    // ========================================================================

    public function testMappingPreservesDeletionTimestamp(): void
    {
        $deletedAt = new \DateTimeImmutable('2026-09-24T10:00:00+00:00');
        $existing = new DoctrineProduct(Uuid::v7(), 'original', $deletedAt);
        $product = new Product(Id::fromString($existing->id->toRfc4122()), 'updated');

        $mapped = new ProductMapper()->toDoctrine($product, $existing);

        self::assertSame($existing, $mapped);
        self::assertSame($deletedAt, $mapped->deletedAt);
    }
}
