<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Entity;

use App\Catalog\Domain\Entity\Product;
use App\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Product::class)]
#[UsesClass(Id::class)]
final class ProductTest extends TestCase
{
    // ========================================================================
    // Properties: expose domain state without allowing external changes
    // ========================================================================

    public function testPropertiesPreserveConstructorValues(): void
    {
        $id = Id::fromString('01994731-abcd-7000-8000-000000000001');

        $product = new Product($id, 'PRODUCT-001');

        self::assertSame($id, $product->id);
        self::assertSame('PRODUCT-001', $product->sku);
    }

    #[DataProvider('readOnlyProperties')]
    public function testPropertiesRejectExternalWrites(string $property): void
    {
        $product = new Product(Id::fromString('01994731-abcd-7000-8000-000000000001'), 'PRODUCT-001');

        $this->expectException(\Error::class);

        $product->{$property} = $product->{$property};
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function readOnlyProperties(): iterable
    {
        yield 'identity' => ['id'];
        yield 'SKU' => ['sku'];
    }
}
