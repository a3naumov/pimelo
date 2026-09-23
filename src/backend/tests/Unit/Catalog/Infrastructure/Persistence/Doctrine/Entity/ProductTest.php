<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Product::class)]
#[UsesClass(Category::class)]
final class ProductTest extends TestCase
{
    // ========================================================================
    // Relations: either side updates both collections without duplicate links
    // ========================================================================

    public function testBothSidesStaySynchronized(): void
    {
        $product = new Product(Uuid::v7(), 'product');
        $category = new Category(Uuid::v7());

        $product->addCategory($category);
        $product->addCategory($category);
        $category->addProduct($product);

        self::assertCount(1, $product->getCategories());
        self::assertSame($category, $product->getCategories()->first());
        self::assertCount(1, $category->getProducts());
        self::assertSame($product, $category->getProducts()->first());

        $category->removeProduct($product);
        self::assertCount(0, $product->getCategories());
        self::assertCount(0, $category->getProducts());

        $category->addProduct($product);
        self::assertCount(1, $product->getCategories());
        $product->removeCategory($category);
        $product->removeCategory($category);
        self::assertCount(0, $product->getCategories());
        self::assertCount(0, $category->getProducts());
    }
}
