<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Catalog\Domain\Service\Category;

use App\Core\Catalog\Domain\Exception\Category\CategorySlugConflictException;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryDetailsException;
use App\Core\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Core\Catalog\Domain\Service\Category\CategorySlugAllocator;
use App\Core\Catalog\Domain\Service\Category\CategorySlugGeneratorInterface;
use App\Shared\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CategorySlugAllocator::class)]
#[UsesClass(CategorySlugConflictException::class)]
#[UsesClass(InvalidCategoryDetailsException::class)]
#[UsesClass(Id::class)]
final class CategorySlugAllocatorTest extends TestCase
{
    // ========================================================================
    // Allocation: automatic suffixes and explicit custom-slug consent
    // ========================================================================

    public function testAllocatesTheFirstFreeSuffixAndExcludesTheCurrentIdentity(): void
    {
        $id = Id::fromString('01994731-abcd-7000-8000-000000000001');
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('slugExists')->willReturnCallback(static function (string $slug, ?Id $exclude) use ($id): bool {
            self::assertSame($id, $exclude);

            return in_array($slug, ['shoes', 'shoes-1'], true);
        });

        self::assertSame('shoes-2', $this->allocator($repository, 'shoes')->allocate('Shoes', excludeId: $id));
        self::assertSame('shoes-2', $this->allocator($repository, 'shoes')->allocate('Shoes', 'SHOES', $id, true));
    }

    public function testRejectsCustomConflictsWithoutConsent(): void
    {
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('slugExists')->willReturnCallback(static fn (string $slug): bool => 'shoes' === $slug);
        $this->expectException(CategorySlugConflictException::class);

        $this->allocator($repository, 'shoes')->allocate('Shoes', 'SHOES');
    }

    public function testUsesFallbackAndReservesSpaceForSuffixes(): void
    {
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        self::assertSame('category', $this->allocator($repository, '')->allocate('!!!'));
        $base = str_repeat('a', 255);
        $repository->method('slugExists')->willReturnCallback(static fn (string $slug): bool => $base === $slug);
        self::assertSame(str_repeat('a', 253).'-1', $this->allocator($repository, $base)->allocate($base));
    }

    public function testRejectsAnEmptyNormalizedCustomSlug(): void
    {
        $this->expectException(InvalidCategoryDetailsException::class);

        $this->allocator($this->createStub(CategoryRepositoryInterface::class), '')->allocate('Shoes', '!!!');
    }

    private function allocator(CategoryRepositoryInterface $repository, string $normalized): CategorySlugAllocator
    {
        $generator = $this->createStub(CategorySlugGeneratorInterface::class);
        $generator->method('normalize')->willReturn($normalized);

        return new CategorySlugAllocator($generator, $repository);
    }
}
