<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Domain\Repository;

use Pimelo\Core\Catalog\Domain\Entity\Product;
use Pimelo\Shared\Identity\ID;

interface ProductRepositoryInterface
{
    /**
     * @return Product[]
     */
    public function all(): array;

    public function findById(ID $id): ?Product;

    public function save(Product $product): Product;

    public function delete(Product $product): void;
}
