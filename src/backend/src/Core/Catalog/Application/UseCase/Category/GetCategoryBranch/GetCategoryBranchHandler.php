<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\GetCategoryBranch;

use App\Core\Catalog\Application\ReadModel\Projector\CategoryProjector;
use App\Core\Catalog\Application\ReadModel\View\CategoryBranchView;
use App\Core\Catalog\Application\ReadModel\View\CategoryView;
use App\Core\Catalog\Domain\Entity\Category;
use App\Core\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Core\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;

final readonly class GetCategoryBranchHandler
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
    public function __invoke(GetCategoryBranchQuery $query): CategoryBranchView
    {
        $branch = $this->categories->findBranch($query->id, $query->includeDeleted);

        if (null === $branch) {
            throw new CategoryNotFoundException('Category not found.');
        }

        $all = array_merge(...array_column($branch->levels, 'categories'));
        $views = [];

        foreach ($this->projector->project($all, $query->includeDeleted) as $view) {
            $views[$view->id] = $view;
        }

        return new CategoryBranchView(
            array_map(static fn (Category $category): CategoryView => $views[$category->id->toString()], $branch->path),
            array_map(static fn (array $level): array => [
                'parentId' => $level['parentId']?->toString(),
                'categories' => array_map(static fn (Category $category): CategoryView => $views[$category->id->toString()], $level['categories']),
            ], $branch->levels),
        );
    }
}
