<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Persistence\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Hierarchy\CategoryBranch;
use App\General\Identity\Id;

interface CategoryRepositoryInterface
{
    /**
     * @return iterable<Category>
     */
    public function findAll(): iterable;

    /**
     * @return list<Category>
     */
    public function findByParentId(?Id $parentId, bool $includeDeleted = false): array;

    /** @param list<Id> $ids
     * @return list<string>
     */
    public function findParentIdsWithChildren(array $ids, bool $includeDeleted = false): array;

    public function findBranch(Id $id, bool $includeDeleted = false): ?CategoryBranch;

    public function findById(Id $id, bool $includeDeleted = false): ?Category;

    /**
     * @throws CategoryNotFoundException
     */
    public function save(Category $category): Category;

    public function delete(Category $category): void;

    public function restore(Id $id): ?Category;

    public function deletePermanently(Id $id): bool;
}
