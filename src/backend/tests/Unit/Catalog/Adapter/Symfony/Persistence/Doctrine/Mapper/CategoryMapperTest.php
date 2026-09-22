<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Adapter\Symfony\Persistence\Doctrine\Mapper;

use App\Catalog\Adapter\Symfony\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Adapter\Symfony\Persistence\Doctrine\Mapper\CategoryMapper;
use App\Catalog\Entity\Category;
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

        self::assertSame($id->toRfc4122(), $category->getId()->toString());
        self::assertSame($id->toRfc4122(), $mapped->getId()->toRfc4122());
        self::assertSame($doctrineCategory, $mapper->toDoctrine($category, $doctrineCategory));
        self::assertNull($category->getParentId());
    }

    // ========================================================================
    // Parent mapping: uses identities without recursively mapping the hierarchy
    // ========================================================================

    public function testMappingSetsAndClearsParentWithoutReplacingEntity(): void
    {
        $parent = new DoctrineCategory(Uuid::v7());
        $child = new DoctrineCategory(Uuid::v7());
        $mapper = new CategoryMapper();
        $category = new Category(Id::fromString($child->getId()->toRfc4122()), Id::fromString($parent->getId()->toRfc4122()));

        $mapped = $mapper->toDoctrine($category, $child, $parent);

        self::assertSame($child, $mapped);
        self::assertSame($parent, $mapped->getParent());
        self::assertEquals($category, $mapper->fromDoctrine($mapped));
        $root = new Category($category->getId());
        self::assertSame($child, $mapper->toDoctrine($root, $child));
        self::assertNull($child->getParent());
        self::assertNull($mapper->fromDoctrine($child)->getParentId());
    }

    public function testMappingRejectsAnUnresolvedParent(): void
    {
        $category = new Category(Id::fromString(Uuid::v7()->toRfc4122()), Id::fromString(Uuid::v7()->toRfc4122()));

        $this->expectException(\InvalidArgumentException::class);

        new CategoryMapper()->toDoctrine($category);
    }
}
