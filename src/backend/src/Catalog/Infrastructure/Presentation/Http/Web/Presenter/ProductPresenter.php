<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Presentation\Http\Web\Presenter;

use App\Catalog\Application\ReadModel\View\ProductView;
use App\Catalog\Infrastructure\Presentation\Http\Web\Resource\Product as ProductResource;

final readonly class ProductPresenter
{
    public function one(ProductView $product): ProductResource
    {
        return new ProductResource($product->id, $product->sku, $product->deletedAt);
    }

    /**
     * @param iterable<ProductView> $products
     *
     * @return list<ProductResource>
     */
    public function many(iterable $products): array
    {
        $resources = [];

        foreach ($products as $product) {
            $resources[] = $this->one($product);
        }

        return $resources;
    }
}
