<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Product\RestoreProduct;

use App\Catalog\Application\ReadModel\Projector\ProductProjector;
use App\Catalog\Application\ReadModel\View\ProductView;
use App\Catalog\Domain\Exception\Product\ProductNotDeletedException;
use App\Catalog\Domain\Exception\Product\ProductNotFoundException;
use App\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;

final readonly class RestoreProductHandler
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private ProductProjector $projector,
    ) {
    }

    /**
     * @throws ProductNotFoundException
     * @throws ProductNotDeletedException
     */
    public function __invoke(RestoreProductCommand $command): ProductView
    {
        $product = $this->products->restore($command->id)
            ?? throw new ProductNotFoundException('Product not found.');

        return $this->projector->one($product);
    }
}
