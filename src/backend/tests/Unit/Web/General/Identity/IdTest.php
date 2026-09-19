<?php

declare(strict_types=1);

namespace App\Tests\Unit\Web\General\Identity;

use App\Web\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Id::class)]
final class IdTest extends TestCase
{
    // ========================================================================
    // Creation: accepts string identities and normalizes their case
    // ========================================================================

    #[DataProvider('identityValues')]
    public function testFromStringNormalizesValue(string $value, string $expected): void
    {
        $id = Id::fromString($value);

        self::assertSame($expected, $id->toString());
        self::assertSame($expected, (string) $id);
    }

    // ========================================================================
    // Equality: compares identity values rather than object references
    // ========================================================================

    public function testEqualsComparesValues(): void
    {
        $first = Id::fromString('01994731-abcd-7000-8000-000000000000');
        $second = Id::fromString('01994731-ABCD-7000-8000-000000000000');
        $different = Id::fromString('01994731-abcd-7000-8000-000000000001');

        self::assertNotSame($first, $second);
        self::assertTrue($first->equals($first));
        self::assertTrue($first->equals($second));
        self::assertTrue($second->equals($first));
        self::assertFalse($first->equals($different));
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function identityValues(): iterable
    {
        yield 'non-UUID identity' => ['product-42', 'product-42'];
        yield 'UUID v7' => ['01994731-abcd-7000-8000-000000000000', '01994731-abcd-7000-8000-000000000000'];
        yield 'uppercase UUID' => ['01994731-ABCD-7000-8000-000000000000', '01994731-abcd-7000-8000-000000000000'];
        yield 'UUID v4 compatibility' => ['550e8400-e29b-41d4-a716-446655440000', '550e8400-e29b-41d4-a716-446655440000'];
        yield 'UUID v1 compatibility' => ['c232ab00-9414-11ec-b3c8-9f6bdeced846', 'c232ab00-9414-11ec-b3c8-9f6bdeced846'];
        yield 'Nil UUID compatibility' => ['00000000-0000-0000-0000-000000000000', '00000000-0000-0000-0000-000000000000'];
        yield 'Max UUID compatibility' => ['FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF', 'ffffffff-ffff-ffff-ffff-ffffffffffff'];
    }
}
