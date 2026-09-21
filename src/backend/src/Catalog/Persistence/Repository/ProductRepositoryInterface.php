<?php

declare(strict_types=1);

namespace App\Catalog\Persistence\Repository;

use App\Catalog\Entity\Product;
use App\General\Identity\Id;

interface ProductRepositoryInterface
{
    /** @return iterable<Product> */
    public function findAll(): iterable;

    public function findById(Id $id): ?Product;

    public function save(Product $product): Product;

    public function delete(Product $product): void;
}
