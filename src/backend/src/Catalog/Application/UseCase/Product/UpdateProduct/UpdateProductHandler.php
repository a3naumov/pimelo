<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Product\UpdateProduct;

use App\Catalog\Application\ReadModel\Projector\ProductProjector;
use App\Catalog\Application\ReadModel\View\ProductView;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Exception\Product\ProductNotFoundException;
use App\Catalog\Domain\Exception\Product\ProductSkuAlreadyExistsException;
use App\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;

final readonly class UpdateProductHandler
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private ProductProjector $projector,
    ) {
    }

    /**
     * @throws ProductNotFoundException
     * @throws ProductSkuAlreadyExistsException
     */
    public function __invoke(UpdateProductCommand $command): ProductView
    {
        $existing = $this->products->findById($command->id)
            ?? throw new ProductNotFoundException('Product not found.');

        return $this->projector->one($this->products->save(new Product($existing->id, $command->sku)));
    }
}
