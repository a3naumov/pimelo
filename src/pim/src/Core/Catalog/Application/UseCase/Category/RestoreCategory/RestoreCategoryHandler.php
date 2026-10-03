<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\RestoreCategory;

use App\Core\Catalog\Application\ReadModel\Projector\CategoryProjector;
use App\Core\Catalog\Application\ReadModel\View\CategoryView;
use App\Core\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Core\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;

final readonly class RestoreCategoryHandler
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
        private CategoryProjector $projector,
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InvalidCategoryHierarchyException
     */
    public function __invoke(RestoreCategoryCommand $command): CategoryView
    {
        $category = $this->categories->restore($command->id);

        if (null === $category) {
            throw new CategoryNotFoundException('Category not found.');
        }

        return $this->projector->one($category, true);
    }
}
