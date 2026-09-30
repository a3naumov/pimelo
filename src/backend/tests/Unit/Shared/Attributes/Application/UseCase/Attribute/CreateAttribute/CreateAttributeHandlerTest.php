<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Attributes\Application\UseCase\Attribute\CreateAttribute;

use App\Shared\Attributes\Application\ReadModel\Projector\AttributeProjector;
use App\Shared\Attributes\Application\ReadModel\View\AttributeView;
use App\Shared\Attributes\Application\UseCase\Attribute\CreateAttribute\CreateAttributeCommand;
use App\Shared\Attributes\Application\UseCase\Attribute\CreateAttribute\CreateAttributeHandler;
use App\Shared\Attributes\Domain\Entity\Attribute;
use App\Shared\Attributes\Domain\Persistence\Repository\AttributeRepositoryInterface;
use App\Shared\General\Identity\Id;
use App\Shared\General\Identity\IdGeneratorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateAttributeHandler::class)]
#[UsesClass(CreateAttributeCommand::class)]
#[UsesClass(AttributeProjector::class)]
#[UsesClass(AttributeView::class)]
#[UsesClass(Attribute::class)]
#[UsesClass(Id::class)]
final class CreateAttributeHandlerTest extends TestCase
{
    // ========================================================================
    // Creation: shared ID generation and persisted data form the result view
    // ========================================================================

    public function testGeneratesIdentityAndProjectsTheSavedResult(): void
    {
        $id = Id::fromString('01994731-abcd-7000-8000-000000000001');
        $date = new \DateTimeImmutable('2026-09-30T10:00:00+00:00');
        $ids = $this->createMock(IdGeneratorInterface::class);
        $ids->expects(self::once())->method('generate')->willReturn($id);
        $repository = $this->createMock(AttributeRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->willReturnCallback(static function (Attribute $attribute) use ($id, $date): Attribute {
            self::assertSame($id, $attribute->id);
            self::assertSame('Color', $attribute->name);
            self::assertNull($attribute->createdAt);
            self::assertNull($attribute->updatedAt);
            self::assertNull($attribute->deletedAt);

            return new Attribute($id, 'Color', createdAt: $date, updatedAt: $date);
        });

        $result = (new CreateAttributeHandler($repository, $ids, new AttributeProjector()))(new CreateAttributeCommand('Color'));

        self::assertSame($id->toString(), $result->id);
        self::assertSame('Color', $result->name);
        self::assertSame($date, $result->createdAt);
        self::assertSame($date, $result->updatedAt);
        self::assertNull($result->deletedAt);
    }
}
