<?php

declare(strict_types=1);

namespace App\Catalog\Application\ReadModel\Projector;

use App\Catalog\Application\ReadModel\View\ProductView;
use App\Catalog\Domain\Entity\Product;

final readonly class ProductProjector
{
    public function one(Product $product): ProductView
    {
        return new ProductView($product->id->toString(), $product->sku, $product->deletedAt);
    }

    /**
     * @param iterable<Product> $products
     *
     * @return list<ProductView>
     */
    public function many(iterable $products): array
    {
        $views = [];

        foreach ($products as $product) {
            $views[] = $this->one($product);
        }

        return $views;
    }
}
