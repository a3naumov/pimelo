<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Catalog\Infrastructure\Presentation\Http\Web\Error;

use App\Core\Catalog\Domain\Exception\Product\ProductNotDeletedException;
use App\Core\Catalog\Domain\Exception\Product\ProductNotFoundException;
use App\Core\Catalog\Domain\Exception\Product\ProductSkuAlreadyExistsException;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Error\ProductExceptionStatusMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ProductExceptionStatusMapper::class)]
final class ProductExceptionStatusMapperTest extends TestCase
{
    // ========================================================================
    // Classification: product failures map to their existing HTTP statuses
    // ========================================================================

    #[DataProvider('productErrors')]
    public function testMapsProductErrors(\Throwable $exception, ?int $status): void
    {
        self::assertSame($status, (new ProductExceptionStatusMapper())->statusFor($exception));
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function productErrors(): iterable
    {
        yield 'missing product' => [new ProductNotFoundException(), Response::HTTP_NOT_FOUND];

        yield 'product is not deleted' => [new ProductNotDeletedException(), Response::HTTP_CONFLICT];

        yield 'duplicate SKU' => [new ProductSkuAlreadyExistsException(), Response::HTTP_CONFLICT];

        yield 'unrelated failure' => [new \RuntimeException(), null];
    }
}
