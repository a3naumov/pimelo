<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Domain\Repository;

use Pimelo\Core\Catalog\Domain\Aggregate\CategoryProduct;

interface CategoryProductRepositoryInterface
{
    public function exists(CategoryProduct $categoryProduct): bool;

    public function save(CategoryProduct $categoryProduct): CategoryProduct;

    public function delete(CategoryProduct $categoryProduct): void;
}
