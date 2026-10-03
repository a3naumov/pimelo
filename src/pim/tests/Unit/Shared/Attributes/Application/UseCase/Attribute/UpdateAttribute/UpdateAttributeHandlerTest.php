<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Attributes\Application\UseCase\Attribute\UpdateAttribute;

use App\Shared\Attributes\Application\ReadModel\Projector\AttributeProjector;
use App\Shared\Attributes\Application\ReadModel\View\AttributeView;
use App\Shared\Attributes\Application\UseCase\Attribute\UpdateAttribute\UpdateAttributeCommand;
use App\Shared\Attributes\Application\UseCase\Attribute\UpdateAttribute\UpdateAttributeHandler;
use App\Shared\Attributes\Domain\Entity\Attribute;
use App\Shared\Attributes\Domain\Exception\Attribute\AttributeNotFoundException;
use App\Shared\Attributes\Domain\Persistence\Repository\AttributeRepositoryInterface;
use App\Shared\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateAttributeHandler::class)]
#[UsesClass(UpdateAttributeCommand::class)]
#[UsesClass(AttributeProjector::class)]
#[UsesClass(AttributeView::class)]
#[UsesClass(Attribute::class)]
#[UsesClass(Id::class)]
#[UsesClass(AttributeNotFoundException::class)]
final class UpdateAttributeHandlerTest extends TestCase
{
    // ========================================================================
    // Rename: identity and creation date survive; persisted update date wins
    // ========================================================================

    public function testPreservesIdentityAndUsesThePersistedTimestamps(): void
    {
        $id = Id::fromString('01994731-abcd-7000-8000-000000000001');
        $created = new \DateTimeImmutable('2026-09-01T10:00:00+00:00');
        $updated = new \DateTimeImmutable('2026-09-20T10:00:00+00:00');
        $saved = new \DateTimeImmutable('2026-09-30T10:00:00+00:00');
        $repository = $this->createMock(AttributeRepositoryInterface::class);
        $repository->expects(self::once())->method('findById')->with($id)->willReturn(new Attribute($id, 'Old', createdAt: $created, updatedAt: $updated));
        $repository->expects(self::once())->method('save')->willReturnCallback(static function (Attribute $attribute) use ($id, $created, $updated, $saved): Attribute {
            self::assertSame($id, $attribute->id);
            self::assertSame('New', $attribute->name);
            self::assertSame($created, $attribute->createdAt);
            self::assertSame($updated, $attribute->updatedAt);
            self::assertNull($attribute->deletedAt);

            return new Attribute($id, 'New', createdAt: $created, updatedAt: $saved);
        });

        $result = (new UpdateAttributeHandler($repository, new AttributeProjector()))(new UpdateAttributeCommand($id, 'New'));

        self::assertSame($id->toString(), $result->id);
        self::assertSame('New', $result->name);
        self::assertSame($created, $result->createdAt);
        self::assertSame($saved, $result->updatedAt);
    }

    // ========================================================================
    // Missing or archived attribute: updates never create a replacement
    // ========================================================================

    public function testMissingAttributeIsNotSaved(): void
    {
        $id = Id::fromString('01994731-abcd-7000-8000-000000000001');
        $repository = $this->createMock(AttributeRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');
        $this->expectException(AttributeNotFoundException::class);

        (new UpdateAttributeHandler($repository, new AttributeProjector()))(new UpdateAttributeCommand($id, 'New'));
    }
}
