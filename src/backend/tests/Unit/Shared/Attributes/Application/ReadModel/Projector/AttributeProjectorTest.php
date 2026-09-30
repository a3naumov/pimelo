<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Attributes\Application\ReadModel\Projector;

use App\Shared\Attributes\Application\ReadModel\Projector\AttributeProjector;
use App\Shared\Attributes\Application\ReadModel\View\AttributeView;
use App\Shared\Attributes\Domain\Entity\Attribute;
use App\Shared\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeProjector::class)]
#[UsesClass(AttributeView::class)]
#[UsesClass(Attribute::class)]
#[UsesClass(Id::class)]
final class AttributeProjectorTest extends TestCase
{
    // ========================================================================
    // Projection: views preserve all fields without exposing domain entities
    // ========================================================================

    public function testProjectsCompleteAttributeData(): void
    {
        $id = Id::fromString('01994731-abcd-7000-8000-000000000001');
        $createdAt = new \DateTimeImmutable('2026-09-01T10:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2026-09-20T10:00:00+00:00');
        $deletedAt = new \DateTimeImmutable('2026-09-30T10:00:00+00:00');

        $view = new AttributeProjector()->one(new Attribute($id, 'Color', $deletedAt, $createdAt, $updatedAt));

        self::assertSame($id->toString(), $view->id);
        self::assertSame('Color', $view->name);
        self::assertSame($createdAt, $view->createdAt);
        self::assertSame($updatedAt, $view->updatedAt);
        self::assertSame($deletedAt, $view->deletedAt);
        self::assertSame(['id', 'name', 'createdAt', 'updatedAt', 'deletedAt'], array_keys(get_object_vars($view)));
    }

    public function testProjectsIterableInOrderAndSupportsEmptyLists(): void
    {
        $date = new \DateTimeImmutable();
        $first = new Attribute(Id::fromString('01994731-abcd-7000-8000-000000000001'), 'First', createdAt: $date, updatedAt: $date);
        $second = new Attribute(Id::fromString('01994731-abcd-7000-8000-000000000002'), 'Second', createdAt: $date, updatedAt: $date);
        $projector = new AttributeProjector();

        self::assertSame(['First', 'Second'], array_map(static fn (AttributeView $view): string => $view->name, $projector->many(new \ArrayIterator([$first, $second]))));
        self::assertSame([], $projector->many([]));
    }

    // ========================================================================
    // Persistence contract: incomplete timestamps cannot become a public view
    // ========================================================================

    #[DataProvider('missingTimestamps')]
    public function testRejectsMissingPersistenceTimestamps(?\DateTimeImmutable $createdAt, ?\DateTimeImmutable $updatedAt): void
    {
        $attribute = new Attribute(Id::fromString('01994731-abcd-7000-8000-000000000001'), 'Color', createdAt: $createdAt, updatedAt: $updatedAt);
        $this->expectException(\LogicException::class);

        new AttributeProjector()->one($attribute);
    }

    // ========================================================================
    // Data providers: combinations with incomplete lifecycle metadata
    // ========================================================================

    public static function missingTimestamps(): iterable
    {
        yield 'missing creation timestamp' => [null, new \DateTimeImmutable()];

        yield 'missing update timestamp' => [new \DateTimeImmutable(), null];

        yield 'both missing' => [null, null];
    }
}
