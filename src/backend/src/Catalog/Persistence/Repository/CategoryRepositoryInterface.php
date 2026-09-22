<?php

declare(strict_types=1);

namespace App\Catalog\Persistence\Repository;

use App\Catalog\Entity\Category;
use App\Catalog\Exception\Category\CategoryHasChildrenException;
use App\Catalog\Exception\Category\CategoryNotFoundException;
use App\Catalog\Exception\Category\InvalidCategoryHierarchyException;
use App\General\Identity\Id;

interface CategoryRepositoryInterface
{
    /** @return iterable<Category> */
    public function findAll(): iterable;

    public function findById(Id $id): ?Category;

    /**
     * @throws CategoryNotFoundException
     * @throws InvalidCategoryHierarchyException
     */
    public function save(Category $category): Category;

    /**
     * @throws CategoryNotFoundException
     * @throws InvalidCategoryHierarchyException
     */
    public function move(Id $id, ?Id $parentId): Category;

    /** @throws CategoryHasChildrenException */
    public function delete(Category $category): void;
}
