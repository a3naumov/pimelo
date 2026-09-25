<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(CategoryMapper::class)]
#[UsesClass(DoctrineCategory::class)]
#[UsesClass(Category::class)]
#[UsesClass(Id::class)]
final class CategoryMapperTest extends TestCase
{
    // ========================================================================
    // Mapping: preserves identity in both directions and reuses managed entities
    // ========================================================================

    public function testMappingPreservesIdentity(): void
    {
        $id = Uuid::fromString('01994731-abcd-7000-8000-000000000000');
        $doctrineCategory = new DoctrineCategory($id);
        $mapper = new CategoryMapper();

        $category = $mapper->fromDoctrine($doctrineCategory);
        $mapped = $mapper->toDoctrine($category);

        self::assertSame($id->toRfc4122(), $category->id->toString());
        self::assertSame($id->toRfc4122(), $mapped->id->toRfc4122());
        self::assertSame($doctrineCategory, $mapper->toDoctrine($category, $doctrineCategory));
        self::assertNull($category->parentId);
        self::assertNull($mapped->deletedAt);
    }

    // ========================================================================
    // Parent mapping: uses identities without recursively mapping the hierarchy
    // ========================================================================

    public function testMappingSetsAndClearsParentWithoutReplacingEntity(): void
    {
        $parent = new DoctrineCategory(Uuid::v7());
        $child = new DoctrineCategory(Uuid::v7());
        $mapper = new CategoryMapper();
        $category = new Category(Id::fromString($child->id->toRfc4122()), Id::fromString($parent->id->toRfc4122()));

        $mapped = $mapper->toDoctrine($category, $child);

        self::assertSame($child, $mapped);
        self::assertEquals($parent->id, $mapped->parentId);
        self::assertEquals($category, $mapper->fromDoctrine($mapped));
        $mappedParentId = $mapped->parentId;
        $mapper->toDoctrine($category, $child);
        self::assertSame($mappedParentId, $child->parentId);
        $root = new Category($category->id);
        self::assertSame($child, $mapper->toDoctrine($root, $child));
        self::assertNull($child->parentId);
        self::assertNull($mapper->fromDoctrine($child)->parentId);
    }

    // ========================================================================
    // Soft deletion: mapping never restores an archived persistence entity
    // ========================================================================

    public function testMappingPreservesDeletionTimestamp(): void
    {
        $deletedAt = new \DateTimeImmutable('2026-09-24T10:00:00+00:00');
        $existing = new DoctrineCategory(Uuid::v7(), $deletedAt);
        $category = new Category(Id::fromString($existing->id->toRfc4122()));

        $mapped = new CategoryMapper()->toDoctrine($category, $existing);

        self::assertSame($existing, $mapped);
        self::assertSame($deletedAt, $mapped->deletedAt);
    }
}
