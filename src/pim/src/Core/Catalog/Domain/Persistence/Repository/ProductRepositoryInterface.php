<?php

declare(strict_types=1);

namespace App\Core\Catalog\Domain\Persistence\Repository;

use App\Core\Catalog\Domain\Entity\Product;
use App\Core\Catalog\Domain\Exception\Product\ProductNotDeletedException;
use App\Core\Catalog\Domain\Exception\Product\ProductSkuAlreadyExistsException;
use App\Shared\General\Identity\Id;

interface ProductRepositoryInterface
{
    /**
     * @return iterable<Product>
     */
    public function findAll(bool $deleted = false): iterable;

    public function findById(Id $id, bool $includeDeleted = false): ?Product;

    /**
     * @throws ProductSkuAlreadyExistsException
     */
    public function save(Product $product): Product;

    public function delete(Product $product): void;

    /**
     * @throws ProductNotDeletedException
     */
    public function restore(Id $id): ?Product;

    /**
     * @throws ProductNotDeletedException
     */
    public function deletePermanently(Id $id): bool;
}
