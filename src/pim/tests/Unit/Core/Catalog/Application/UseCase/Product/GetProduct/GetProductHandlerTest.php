<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Catalog\Application\UseCase\Product\GetProduct;

use App\Core\Catalog\Application\ReadModel\Projector\ProductProjector;
use App\Core\Catalog\Application\ReadModel\View\ProductView;
use App\Core\Catalog\Application\UseCase\Product\GetProduct\GetProductHandler;
use App\Core\Catalog\Application\UseCase\Product\GetProduct\GetProductQuery;
use App\Core\Catalog\Domain\Entity\Product;
use App\Core\Catalog\Domain\Exception\Product\ProductNotFoundException;
use App\Core\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use App\Shared\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetProductHandler::class)]
#[UsesClass(GetProductQuery::class)]
#[UsesClass(ProductProjector::class)]
#[UsesClass(ProductView::class)]
#[UsesClass(Product::class)]
#[UsesClass(Id::class)]
#[UsesClass(ProductNotFoundException::class)]
final class GetProductHandlerTest extends TestCase
{
    // ========================================================================
    // Lookup: the requested visibility is passed to the repository
    // ========================================================================

    public function testReturnsDeletedProductWhenRequested(): void
    {
        $id = Id::fromString('01994731-abcd-7000-8000-000000000001');
        $product = new Product($id, 'SKU', new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::once())->method('findById')->with($id, true)->willReturn($product);

        $result = (new GetProductHandler($repository, new ProductProjector()))(new GetProductQuery($id, true));

        self::assertSame($id->toString(), $result->id);
        self::assertSame('SKU', $result->sku);
        self::assertSame($product->deletedAt, $result->deletedAt);
    }

    // ========================================================================
    // Missing product: the application exposes a domain exception
    // ========================================================================

    public function testMissingProductThrowsNotFound(): void
    {
        $id = Id::fromString('01994731-abcd-7000-8000-000000000001');
        $repository = $this->createStub(ProductRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $this->expectException(ProductNotFoundException::class);

        (new GetProductHandler($repository, new ProductProjector()))(new GetProductQuery($id));
    }
}
