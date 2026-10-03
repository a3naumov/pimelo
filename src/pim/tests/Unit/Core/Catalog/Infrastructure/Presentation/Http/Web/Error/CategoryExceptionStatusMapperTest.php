<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Catalog\Infrastructure\Presentation\Http\Web\Error;

use App\Core\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Error\CategoryExceptionStatusMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(CategoryExceptionStatusMapper::class)]
final class CategoryExceptionStatusMapperTest extends TestCase
{
    // ========================================================================
    // Classification: category failures map to their existing HTTP statuses
    // ========================================================================

    #[DataProvider('categoryErrors')]
    public function testMapsCategoryErrors(\Throwable $exception, ?int $status): void
    {
        self::assertSame($status, (new CategoryExceptionStatusMapper())->statusFor($exception));
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function categoryErrors(): iterable
    {
        yield 'missing category' => [new CategoryNotFoundException(), Response::HTTP_NOT_FOUND];

        yield 'invalid hierarchy' => [new InvalidCategoryHierarchyException(), Response::HTTP_CONFLICT];

        yield 'unrelated failure' => [new \RuntimeException(), null];
    }
}
