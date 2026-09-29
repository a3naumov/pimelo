<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\UseCase\CategoryProduct\AttachProductToCategory;

use App\Catalog\Application\UseCase\CategoryProduct\AttachProductToCategory\AttachProductToCategoryCommand;
use App\Catalog\Application\UseCase\CategoryProduct\AttachProductToCategory\AttachProductToCategoryHandler;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Exception\Product\ProductNotFoundException;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Persistence\Repository\ProductCategoryRepositoryInterface;
use App\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use App\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttachProductToCategoryHandler::class)]
#[UsesClass(AttachProductToCategoryCommand::class)]
#[UsesClass(Product::class)]
#[UsesClass(Category::class)]
#[UsesClass(Id::class)]
#[UsesClass(ProductNotFoundException::class)]
#[UsesClass(CategoryNotFoundException::class)]
final class AttachProductToCategoryHandlerTest extends TestCase
{
    // ========================================================================
    // Association: existing resources are linked by the repository
    // ========================================================================

    public function testAttachesExistingProductAndCategory(): void
    {
        $product = new Product(Id::fromString('01994731-abcd-7000-8000-000000000001'), 'SKU');
        $category = new Category(Id::fromString('01994731-abcd-7000-8000-000000000002'));
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('findById')->willReturn($product);
        $categories = $this->createStub(CategoryRepositoryInterface::class);
        $categories->method('findById')->willReturn($category);
        $links = $this->createMock(ProductCategoryRepositoryInterface::class);
        $links->expects(self::once())->method('attach')->with($product, $category);

        (new AttachProductToCategoryHandler($products, $categories, $links))(new AttachProductToCategoryCommand($product->id, $category->id));
    }

    // ========================================================================
    // Missing resources: no association is written
    // ========================================================================

    #[DataProvider('missingResources')]
    public function testMissingResourceIsRejected(bool $missingProduct, string $exceptionClass): void
    {
        $product = new Product(Id::fromString('01994731-abcd-7000-8000-000000000001'), 'SKU');
        $category = new Category(Id::fromString('01994731-abcd-7000-8000-000000000002'));
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('findById')->willReturn($missingProduct ? null : $product);
        $categories = $this->createStub(CategoryRepositoryInterface::class);
        $categories->method('findById')->willReturn($missingProduct ? $category : null);
        $links = $this->createMock(ProductCategoryRepositoryInterface::class);
        $links->expects(self::never())->method('attach');
        $this->expectException($exceptionClass);

        (new AttachProductToCategoryHandler($products, $categories, $links))(new AttachProductToCategoryCommand($product->id, $category->id));
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function missingResources(): iterable
    {
        yield 'missing product' => [true, ProductNotFoundException::class];

        yield 'missing category' => [false, CategoryNotFoundException::class];
    }
}
