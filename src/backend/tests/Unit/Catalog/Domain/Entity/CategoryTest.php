<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Entity;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Category::class)]
#[UsesClass(Id::class)]
#[UsesClass(InvalidCategoryHierarchyException::class)]
final class CategoryTest extends TestCase
{
    // ========================================================================
    // Properties: external writes cannot bypass category movement rules
    // ========================================================================

    #[DataProvider('readOnlyProperties')]
    public function testPropertiesRejectExternalWrites(string $property): void
    {
        $category = new Category(Id::fromString('01994731-abcd-7000-8000-000000000001'));

        $this->expectException(\Error::class);

        $category->{$property} = $category->id;
    }

    // ========================================================================
    // Movement: returns a new state without changing the original category
    // ========================================================================

    public function testMovePreservesIdentityAndOriginalState(): void
    {
        $category = new Category(Id::fromString('01994731-abcd-7000-8000-000000000001'));
        $parentId = Id::fromString('01994731-abcd-7000-8000-000000000002');

        $moved = $category->moveTo($parentId);
        $root = $moved->moveTo(null);

        self::assertNotSame($category, $moved);
        self::assertTrue($category->id->equals($moved->id));
        self::assertNull($category->parentId);
        self::assertTrue($parentId->equals($moved->parentId));
        self::assertNull($root->parentId);
        self::assertEquals($moved, $moved->moveTo($parentId));
    }

    // ========================================================================
    // Identity: self-parenting is invalid during construction and movement
    // ========================================================================

    public function testConstructionRejectsSelfParentByValue(): void
    {
        $id = Id::fromString('01994731-abcd-7000-8000-000000000001');

        $this->expectException(InvalidCategoryHierarchyException::class);
        $this->expectExceptionMessage('A category cannot be its own parent.');

        new Category($id, Id::fromString(strtoupper($id->toString())));
    }

    public function testMoveRejectsSelfParentWithoutChangingState(): void
    {
        $category = new Category(Id::fromString('01994731-abcd-7000-8000-000000000001'));

        try {
            $category->moveTo($category->id);
            self::fail('Self-parenting must be rejected.');
        } catch (InvalidCategoryHierarchyException $exception) {
            self::assertSame('A category cannot be its own parent.', $exception->getMessage());
            self::assertNull($category->parentId);
        }
    }
    // ========================================================================
    // Data providers
    // ========================================================================

    public static function readOnlyProperties(): iterable
    {
        yield 'identity' => ['id'];
        yield 'parent identity' => ['parentId'];
    }
}
