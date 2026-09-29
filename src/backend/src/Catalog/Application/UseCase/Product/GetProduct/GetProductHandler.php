<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Product\GetProduct;

use App\Catalog\Application\ReadModel\Projector\ProductProjector;
use App\Catalog\Application\ReadModel\View\ProductView;
use App\Catalog\Domain\Exception\Product\ProductNotFoundException;
use App\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;

final readonly class GetProductHandler
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private ProductProjector $projector,
    ) {
    }

    /**
     * @throws ProductNotFoundException
     */
    public function __invoke(GetProductQuery $query): ProductView
    {
        $product = $this->products->findById($query->id, $query->includeDeleted)
            ?? throw new ProductNotFoundException('Product not found.');

        return $this->projector->one($product);
    }
}
