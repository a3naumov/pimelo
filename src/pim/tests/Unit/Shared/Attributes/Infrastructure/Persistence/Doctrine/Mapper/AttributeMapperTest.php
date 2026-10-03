<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Attributes\Infrastructure\Persistence\Doctrine\Mapper;

use App\Shared\Attributes\Domain\Entity\Attribute;
use App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Entity\Attribute as DoctrineAttribute;
use App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Mapper\AttributeMapper;
use App\Shared\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(AttributeMapper::class)]
#[UsesClass(DoctrineAttribute::class)]
#[UsesClass(Attribute::class)]
#[UsesClass(Id::class)]
final class AttributeMapperTest extends TestCase
{
    // ========================================================================
    // From Doctrine: preserves identity and fields in the domain model
    // ========================================================================

    public function testFromDoctrinePreservesIdentityAndName(): void
    {
        $id = Uuid::fromString('01994731-0123-7000-8000-000000000000');
        $doctrineAttribute = new DoctrineAttribute(id: $id, name: 'attribute-1');

        $attribute = new AttributeMapper()->fromDoctrine($doctrineAttribute);

        self::assertSame($id->toRfc4122(), $attribute->id->toString());
        self::assertSame('attribute-1', $attribute->name);
    }

    // ========================================================================
    // To Doctrine: creates a new entity from the domain model
    // ========================================================================

    public function testToDoctrineCreatesEntityWithIdentityAndName(): void
    {
        $id = Id::fromString('01994731-0123-7000-8000-000000000000');
        $attribute = new Attribute(id: $id, name: 'attribute-1');

        $doctrineAttribute = new AttributeMapper()->toDoctrine($attribute);

        self::assertSame($id->toString(), $doctrineAttribute->id->toRfc4122());
        self::assertSame('attribute-1', $doctrineAttribute->name);
        self::assertNull($doctrineAttribute->deletedAt);
    }

    // ========================================================================
    // To Doctrine: updates the existing entity without replacing it or its ID
    // ========================================================================

    public function testToDoctrineUpdatesExistingEntityInPlace(): void
    {
        $id = Uuid::fromString('01994731-0123-7000-8000-000000000000');
        $doctrineAttribute = new DoctrineAttribute(id: $id, name: 'original-name');
        $attribute = new Attribute(id: Id::fromString($id->toRfc4122()), name: 'updated-name');

        $updated = new AttributeMapper()->toDoctrine($attribute, $doctrineAttribute);

        self::assertSame($doctrineAttribute, $updated);
        self::assertSame($id, $updated->id);
        self::assertSame('updated-name', $updated->name);
    }

    // ========================================================================
    // Soft deletion: mapping never restores an archived persistence entity
    // ========================================================================

    public function testMappingPreservesDeletionTimestamp(): void
    {
        $deletedAt = new \DateTimeImmutable('2026-09-24T10:00:00+00:00');
        $existing = new DoctrineAttribute(Uuid::v7(), 'original', $deletedAt);
        $attribute = new Attribute(Id::fromString($existing->id->toRfc4122()), 'updated');

        $mapped = new AttributeMapper()->toDoctrine($attribute, $existing);

        self::assertSame($existing, $mapped);
        self::assertSame($deletedAt, $mapped->deletedAt);
        self::assertSame($deletedAt, new AttributeMapper()->fromDoctrine($mapped)->deletedAt);
    }

    // ========================================================================
    // Lifecycle metadata: persistence owns creation and update timestamps
    // ========================================================================

    public function testMappingPreservesAllLifecycleDatesOnExistingEntity(): void
    {
        $createdAt = new \DateTimeImmutable('2026-09-01T10:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2026-09-20T10:00:00+00:00');
        $deletedAt = new \DateTimeImmutable('2026-09-30T10:00:00+00:00');
        $existing = new DoctrineAttribute(Uuid::v7(), 'Original', $deletedAt, $createdAt, $updatedAt);
        $mapper = new AttributeMapper();
        $attribute = $mapper->fromDoctrine($existing);

        self::assertSame($createdAt, $attribute->createdAt);
        self::assertSame($updatedAt, $attribute->updatedAt);
        self::assertSame($deletedAt, $attribute->deletedAt);
        $mapped = $mapper->toDoctrine(new Attribute($attribute->id, 'Changed'), $existing);
        self::assertSame($createdAt, $mapped->createdAt);
        self::assertSame($updatedAt, $mapped->updatedAt);
        self::assertSame($deletedAt, $mapped->deletedAt);
        self::assertSame('Changed', $mapped->name);
    }
}
