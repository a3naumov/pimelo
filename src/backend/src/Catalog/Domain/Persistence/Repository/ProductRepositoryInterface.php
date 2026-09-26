<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Persistence\Repository;

use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Exception\Product\ProductNotDeletedException;
use App\General\Identity\Id;

interface ProductRepositoryInterface
{
    /** @return iterable<Product> */
    public function findAll(bool $deleted = false): iterable;

    public function findById(Id $id, bool $includeDeleted = false): ?Product;

    public function save(Product $product): Product;

    public function delete(Product $product): void;

    /** @throws ProductNotDeletedException */
    public function restore(Id $id): ?Product;

    /** @throws ProductNotDeletedException */
    public function deletePermanently(Id $id): bool;
}
