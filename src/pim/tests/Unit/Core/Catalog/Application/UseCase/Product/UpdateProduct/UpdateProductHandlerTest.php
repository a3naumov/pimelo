<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Catalog\Application\UseCase\Product\UpdateProduct;

use App\Core\Catalog\Application\ReadModel\Projector\ProductProjector;
use App\Core\Catalog\Application\ReadModel\View\ProductView;
use App\Core\Catalog\Application\UseCase\Product\UpdateProduct\UpdateProductCommand;
use App\Core\Catalog\Application\UseCase\Product\UpdateProduct\UpdateProductHandler;
use App\Core\Catalog\Domain\Entity\Product;
use App\Core\Catalog\Domain\Exception\Product\ProductNotFoundException;
use App\Core\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use App\Shared\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateProductHandler::class)]
#[UsesClass(ProductProjector::class)]
#[UsesClass(ProductView::class)]
#[UsesClass(UpdateProductCommand::class)]
#[UsesClass(Product::class)]
#[UsesClass(Id::class)]
#[UsesClass(ProductNotFoundException::class)]
final class UpdateProductHandlerTest extends TestCase
{
    // ========================================================================
    // Update: the SKU changes without replacing product identity
    // ========================================================================

    public function testPreservesIdentityWhenChangingSku(): void
    {
        $id = Id::fromString('01994731-abcd-7000-8000-000000000001');
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::once())->method('findById')->with($id)->willReturn(new Product($id, 'OLD'));
        $repository->expects(self::once())->method('save')->willReturnCallback(static function (Product $product) use ($id): Product {
            self::assertSame($id, $product->id);
            self::assertSame('NEW', $product->sku);

            return $product;
        });

        $result = (new UpdateProductHandler($repository, new ProductProjector()))(new UpdateProductCommand($id, 'NEW'));

        self::assertSame($id->toString(), $result->id);
        self::assertSame('NEW', $result->sku);
    }

    // ========================================================================
    // Missing product: updates never create a replacement
    // ========================================================================

    public function testMissingProductIsNotSaved(): void
    {
        $id = Id::fromString('01994731-abcd-7000-8000-000000000001');
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');
        $this->expectException(ProductNotFoundException::class);

        (new UpdateProductHandler($repository, new ProductProjector()))(new UpdateProductCommand($id, 'NEW'));
    }
}
