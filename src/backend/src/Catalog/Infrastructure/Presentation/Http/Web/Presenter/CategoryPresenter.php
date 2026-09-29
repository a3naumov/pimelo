<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Presentation\Http\Web\Presenter;

use App\Catalog\Application\ReadModel\View\CategoryBranchView;
use App\Catalog\Application\ReadModel\View\CategoryView;
use App\Catalog\Infrastructure\Presentation\Http\Web\Resource\Category as CategoryResource;
use App\Catalog\Infrastructure\Presentation\Http\Web\Resource\CategoryBranch as CategoryBranchResource;

final readonly class CategoryPresenter
{
    public function one(CategoryView $view): CategoryResource
    {
        return new CategoryResource(
            $view->id,
            $view->parentId,
            $view->hasChildren,
            $view->deletedAt,
        );
    }

    /**
     * @param iterable<CategoryView> $views
     *
     * @return list<CategoryResource>
     */
    public function many(iterable $views): array
    {
        $resources = [];

        foreach ($views as $view) {
            $resources[] = $this->one($view);
        }

        return $resources;
    }

    public function branch(CategoryBranchView $branch): CategoryBranchResource
    {
        return new CategoryBranchResource(
            $this->many($branch->path),
            array_map(fn (array $level): array => [
                'parent_id' => $level['parentId'],
                'categories' => $this->many($level['categories']),
            ], $branch->levels),
        );
    }
}
