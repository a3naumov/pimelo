<?php

declare(strict_types=1);

namespace App\Core\Catalog\Domain\Service\Category;

use App\Core\Catalog\Domain\Entity\Category;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Core\Catalog\Domain\Hierarchy\CategoryAncestryInterface;

final readonly class CategoryMover
{
    public function __construct(private CategoryAncestryInterface $ancestry)
    {
    }

    /**
     * @throws InvalidCategoryHierarchyException
     */
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
