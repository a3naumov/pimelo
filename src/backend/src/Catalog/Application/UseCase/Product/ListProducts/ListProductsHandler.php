<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Product\ListProducts;

use App\Catalog\Application\ReadModel\Projector\ProductProjector;
use App\Catalog\Application\ReadModel\View\ProductView;
use App\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;

final readonly class ListProductsHandler
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private ProductProjector $projector,
    ) {
    }

    /**
     * @return list<ProductView>
     */
    public function __invoke(ListProductsQuery $query): array
    {
        return $this->projector->many($this->products->findAll(deleted: $query->deleted));
    }
}
