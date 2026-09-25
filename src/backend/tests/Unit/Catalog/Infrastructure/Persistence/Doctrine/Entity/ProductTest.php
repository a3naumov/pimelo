<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Product::class)]
#[UsesClass(Category::class)]
final class ProductTest extends TestCase
{
    // ========================================================================
    // Properties: setters remain writable; identity and managed state do not
    // ========================================================================

    public function testWritablePropertiesUpdateSkuAndParent(): void
    {
        $product = new Product(Uuid::v7(), 'original');
        $category = new Category(Uuid::v7());
        $parent = new Category(Uuid::v7());

        $product->sku = 'updated';
        $category->parentId = $parent->id;

        self::assertSame('updated', $product->sku);
        self::assertSame($parent->id, $category->parentId);

        $category->parentId = null;

        self::assertNull($category->parentId);
    }

    #[DataProvider('readOnlyProperties')]
    public function testManagedPropertiesRejectExternalWrites(string $entity, string $property): void
    {
        $object = 'product' === $entity ? new Product(Uuid::v7(), 'product') : new Category(Uuid::v7());

        $this->expectException(\Error::class);

        $object->{$property} = $object->{$property};
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function readOnlyProperties(): iterable
    {
        foreach (['product', 'category'] as $entity) {
            foreach (['id', 'createdAt', 'updatedAt', 'deletedAt'] as $property) {
                yield $entity.' '.$property => [$entity, $property];
            }
        }
    }
}
