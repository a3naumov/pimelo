<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Catalog\Infrastructure\Slug;

use App\Core\Catalog\Infrastructure\Slug\SymfonyCategorySlugGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SymfonyCategorySlugGenerator::class)]
final class SymfonyCategorySlugGeneratorTest extends TestCase
{
    // ========================================================================
    // Normalization: locale-independent ASCII slugs for customer names
    // ========================================================================

    #[DataProvider('names')]
    public function testNormalizesNames(string $value, string $expected): void
    {
        self::assertSame($expected, new SymfonyCategorySlugGenerator()->normalize($value));
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function names(): iterable
    {
        yield 'spaces and capitals' => ['  Summer Shoes  ', 'summer-shoes'];

        yield 'accents and symbols' => ['Café & Tea', 'cafe-and-tea'];

        yield 'punctuation' => ['!!!', ''];

        yield 'Cyrillic transliteration' => ['Детская обувь', 'detskaa-obuv'];
    }
}
