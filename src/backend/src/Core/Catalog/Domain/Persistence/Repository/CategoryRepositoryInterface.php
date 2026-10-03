<?php

declare(strict_types=1);

namespace App\Core\Catalog\Domain\Persistence\Repository;

use App\Core\Catalog\Domain\Entity\Category;
use App\Core\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Core\Catalog\Domain\Exception\Category\CategorySlugConflictException;
use App\Core\Catalog\Domain\Hierarchy\CategoryBranch;
use App\Shared\General\Identity\Id;

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

    public function slugExists(string $slug, ?Id $excludeId = null): bool;

    /**
     * @throws CategoryNotFoundException
     * @throws CategorySlugConflictException
     */
    public function save(Category $category): Category;

    public function delete(Category $category): void;

    public function restore(Id $id): ?Category;

    public function deletePermanently(Id $id): bool;
}
