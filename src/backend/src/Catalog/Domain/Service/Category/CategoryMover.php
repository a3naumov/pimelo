<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Service\Category;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Domain\Hierarchy\CategoryAncestryInterface;

final readonly class CategoryMover
{
    public function __construct(private CategoryAncestryInterface $ancestry)
    {
    }

    /** @throws InvalidCategoryHierarchyException */
    public function move(Category $category, ?Category $parent): Category
    {
        $moved = $category->moveTo($parent?->id);

        if (null !== $parent) {
            $ancestry = $this->ancestry->inspect($category->id, $parent->id);

            if ($ancestry->isAncestorOrSelf || $ancestry->hasCycle) {
                throw new InvalidCategoryHierarchyException('Moving this category would create a cycle.');
            }
        }

        return $moved;
    }
}
