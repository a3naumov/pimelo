<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Category::class)]
final class CategoryTest extends TestCase
{
    // ========================================================================
    // Parent identity: stores and clears a UUID without loading another entity
    // ========================================================================

    public function testParentIdentityCanBeSetAndCleared(): void
    {
        $parentId = Uuid::v7();
        $category = new Category(Uuid::v7(), parentId: $parentId);

        self::assertSame($parentId, $category->parentId);

        $category->parentId = null;

        self::assertNull($category->parentId);
    }
}
