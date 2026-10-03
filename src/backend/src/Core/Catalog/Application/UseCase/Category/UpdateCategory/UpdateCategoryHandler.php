<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\UpdateCategory;

use App\Core\Catalog\Application\ReadModel\Projector\CategoryProjector;
use App\Core\Catalog\Application\ReadModel\View\CategoryView;
use App\Core\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Core\Catalog\Domain\Exception\Category\CategorySlugConflictException;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryDetailsException;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Core\Catalog\Domain\Hierarchy\CategoryHierarchyTransactionInterface;
use App\Core\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Core\Catalog\Domain\Service\Category\CategoryMover;
use App\Core\Catalog\Domain\Service\Category\CategorySlugAllocator;

final readonly class UpdateCategoryHandler
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
        private CategoryMover $mover,
        private CategorySlugAllocator $slugs,
        private CategoryHierarchyTransactionInterface $transaction,
        private CategoryProjector $projector,
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CategorySlugConflictException
     * @throws InvalidCategoryDetailsException
     * @throws InvalidCategoryHierarchyException
     */
    public function __invoke(UpdateCategoryCommand $command): CategoryView
    {
        return $this->transaction->run(function () use ($command): CategoryView {
            $category = $this->categories->findById($command->categoryId);

            if (null === $category) {
                throw new CategoryNotFoundException('Category not found.');
            }

            $parent = null === $command->parentId ? null : $this->categories->findById($command->parentId);

            if (null !== $command->parentId && null === $parent) {
                throw new CategoryNotFoundException('Parent category not found.');
            }

            $name = null === $command->name ? $category->name : trim($command->name);
            $slug = null === $command->slug ? $category->slug : $this->slugs->allocate($name, $command->slug, $category->id, $command->allowSlugSuffix);
            $updated = $this->mover->move($category->rename($name, $slug), $parent);

            return $this->projector->one($this->categories->save($updated));
        });
    }
}
