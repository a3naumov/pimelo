<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Product\RestoreProduct;

use App\Core\Catalog\Application\ReadModel\Projector\ProductProjector;
use App\Core\Catalog\Application\ReadModel\View\ProductView;
use App\Core\Catalog\Domain\Exception\Product\ProductNotDeletedException;
use App\Core\Catalog\Domain\Exception\Product\ProductNotFoundException;
use App\Core\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;

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
