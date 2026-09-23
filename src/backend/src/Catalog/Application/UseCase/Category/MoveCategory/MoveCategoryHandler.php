<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Category\MoveCategory;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Domain\Hierarchy\CategoryHierarchyTransactionInterface;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Service\Category\CategoryMover;

final readonly class MoveCategoryHandler
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
        private CategoryMover $mover,
        private CategoryHierarchyTransactionInterface $transaction,
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InvalidCategoryHierarchyException
     */
    public function __invoke(MoveCategoryCommand $command): Category
    {
        return $this->transaction->run(function () use ($command): Category {
            $category = $this->categories->findById($command->categoryId);

            if (null === $category) {
                throw new CategoryNotFoundException('Category not found.');
            }

            $parent = null === $command->parentId ? null : $this->categories->findById($command->parentId);

            if (null !== $command->parentId && null === $parent) {
                throw new CategoryNotFoundException('Parent category not found.');
            }

            return $this->categories->save($this->mover->move($category, $parent));
        });
    }
}
