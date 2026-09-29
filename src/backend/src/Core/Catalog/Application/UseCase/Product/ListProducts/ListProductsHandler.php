<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Product\ListProducts;

use App\Core\Catalog\Application\ReadModel\Projector\ProductProjector;
use App\Core\Catalog\Application\ReadModel\View\ProductView;
use App\Core\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;

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
