<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Catalog\Infrastructure\Presentation\Http\Web\Resource;

use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Resource\Category;
use App\Core\Catalog\Infrastructure\Presentation\Http\Web\Resource\CategoryBranch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CategoryBranch::class)]
#[UsesClass(Category::class)]
final class CategoryBranchTest extends TestCase
{
    // ========================================================================
    // Serialization: path and sibling levels retain the branch HTTP shape
    // ========================================================================

    public function testSerializesPathAndLevels(): void
    {
        $category = new Category('01994731-abcd-7000-8000-000000000001', 'Category', 'category', null, false);
        $resource = new CategoryBranch(
            [$category],
            [['parent_id' => null, 'categories' => [$category]]],
        );

        self::assertSame([
            'path' => [[
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => null,
                'has_children' => false,
                'deleted_at' => null,
            ]],
            'levels' => [[
                'parent_id' => null,
                'categories' => [[
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'parent_id' => null,
                    'has_children' => false,
                    'deleted_at' => null,
                ]],
            ]],
        ], json_decode(json_encode($resource, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
    }
}
