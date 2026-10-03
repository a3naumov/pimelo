<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Catalog\Application\ReadModel\Projector;

use App\Core\Catalog\Application\ReadModel\Projector\ProductProjector;
use App\Core\Catalog\Application\ReadModel\View\ProductView;
use App\Core\Catalog\Domain\Entity\Product;
use App\Shared\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductProjector::class)]
#[UsesClass(ProductView::class)]
#[UsesClass(Product::class)]
#[UsesClass(Id::class)]
final class ProductProjectorTest extends TestCase
{
    // ========================================================================
    // Projection: output contains product data without a domain entity
    // ========================================================================

    public function testProjectsProductData(): void
    {
        $id = Id::fromString('01994731-abcd-7000-8000-000000000001');
        $deletedAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $product = new Product($id, 'SKU', $deletedAt);

        $view = (new ProductProjector())->one($product);

        self::assertSame($id->toString(), $view->id);
        self::assertSame('SKU', $view->sku);
        self::assertSame($deletedAt, $view->deletedAt);
        self::assertSame(['id', 'sku', 'deletedAt'], array_keys(get_object_vars($view)));
    }

    public function testProjectsProductListInOrder(): void
    {
        $first = new Product(Id::fromString('01994731-abcd-7000-8000-000000000001'), 'FIRST');
        $second = new Product(Id::fromString('01994731-abcd-7000-8000-000000000002'), 'SECOND');

        $views = (new ProductProjector())->many([$first, $second]);

        self::assertSame(['FIRST', 'SECOND'], array_map(static fn (ProductView $view): string => $view->sku, $views));
    }
}
