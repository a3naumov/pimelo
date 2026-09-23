<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    // ========================================================================
    // Consistency: rejects missing or mismatched parents before changing state
    // ========================================================================

    public function testMappingRejectsAnUnresolvedParent(): void
    {
        $category = new Category(Id::fromString(Uuid::v7()->toRfc4122()), Id::fromString(Uuid::v7()->toRfc4122()));

        $this->expectException(\InvalidArgumentException::class);

        new CategoryMapper()->toDoctrine($category);
    }

    #[DataProvider('inconsistentParents')]
    public function testInvalidParentLeavesExistingEntityUnchanged(?string $parentId, ?string $resolvedParentId): void
    {
        $originalParent = new DoctrineCategory(Uuid::v7());
        $existing = new DoctrineCategory(Uuid::v7());
        $existing->setParent($originalParent);
        $category = new Category(
            Id::fromString($existing->getId()->toRfc4122()),
            null === $parentId ? null : Id::fromString($parentId),
        );
        $resolvedParent = null === $resolvedParentId ? null : new DoctrineCategory(Uuid::fromString($resolvedParentId));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The resolved parent must match the category parent identity.');

        try {
            new CategoryMapper()->toDoctrine($category, $existing, $resolvedParent);
        } finally {
            self::assertSame($originalParent, $existing->getParent());
        }
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    /** @return iterable<string, array{?string, ?string}> */
    public static function inconsistentParents(): iterable
    {
        $expected = '01994731-abcd-7000-8000-000000000001';
        $other = '01994731-abcd-7000-8000-000000000002';

        yield 'missing resolved parent' => [$expected, null];
        yield 'different parent identity' => [$expected, $other];
        yield 'unexpected parent for a root' => [null, $other];
    }
}
