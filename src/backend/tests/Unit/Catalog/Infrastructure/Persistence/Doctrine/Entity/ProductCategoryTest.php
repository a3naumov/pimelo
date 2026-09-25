<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(ProductCategory::class)]
final class ProductCategoryTest extends TestCase
{
    // ========================================================================
    // Identity: a link consists of two read-only UUIDs
    // ========================================================================

    public function testConstructorPreservesBothIdentities(): void
    {
        $productId = Uuid::v7();
        $categoryId = Uuid::v7();

        $link = new ProductCategory($productId, $categoryId);

        self::assertSame($productId, $link->productId);
        self::assertSame($categoryId, $link->categoryId);
    }

    #[DataProvider('identityProperties')]
    public function testIdentitiesCannotBeChanged(string $property): void
    {
        $link = new ProductCategory(Uuid::v7(), Uuid::v7());

        $this->expectException(\Error::class);

        $link->{$property} = Uuid::v7();
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function identityProperties(): iterable
    {
        yield 'product' => ['productId'];
        yield 'category' => ['categoryId'];
    }
}
