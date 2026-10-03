<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Attributes\Infrastructure\Presentation\Http\Web\Resource;

use App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Resource\Attribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Attribute::class)]
final class AttributeTest extends TestCase
{
    // ========================================================================
    // JSON contract: lifecycle dates are ISO 8601 strings, not date objects
    // ========================================================================

    public function testSerializesEveryFieldAndPreservesTimezone(): void
    {
        $attribute = new Attribute(
            '01994731-abcd-7000-8000-000000000001',
            'Color',
            new \DateTimeImmutable('2026-09-01T10:00:00+04:00'),
            new \DateTimeImmutable('2026-09-20T10:00:00+04:00'),
            new \DateTimeImmutable('2026-09-30T10:00:00+04:00'),
        );

        self::assertSame([
            'id' => '01994731-abcd-7000-8000-000000000001',
            'name' => 'Color',
            'created_at' => '2026-09-01T10:00:00+04:00',
            'updated_at' => '2026-09-20T10:00:00+04:00',
            'deleted_at' => '2026-09-30T10:00:00+04:00',
        ], $attribute->jsonSerialize());
    }

    public function testActiveAttributeIncludesNullDeletionDate(): void
    {
        $date = new \DateTimeImmutable('2026-09-30T10:00:00+00:00');
        $attribute = new Attribute('01994731-abcd-7000-8000-000000000001', 'Color', $date, $date);

        self::assertArrayHasKey('deleted_at', $attribute->jsonSerialize());
        self::assertNull($attribute->jsonSerialize()['deleted_at']);
    }
}
