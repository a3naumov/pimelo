<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Domain\Repository;

use Pimelo\Core\Catalog\Domain\Entity\Category;
use Pimelo\Shared\Identity\ID;

interface CategoryRepositoryInterface
{
    /**
     * @return Category[]
     */
    public function all(): array;

    public function findById(ID $id): ?Category;

    public function save(Category $category): Category;

    public function delete(Category $category): void;
}
