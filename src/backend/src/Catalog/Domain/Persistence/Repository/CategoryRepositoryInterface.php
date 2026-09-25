<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Persistence\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\General\Identity\Id;

interface CategoryRepositoryInterface
{
    /** @return iterable<Category> */
    public function findAll(): iterable;

    public function findById(Id $id): ?Category;

    /**
     * @throws CategoryNotFoundException
     */
    public function save(Category $category): Category;

    public function delete(Category $category): void;
}
