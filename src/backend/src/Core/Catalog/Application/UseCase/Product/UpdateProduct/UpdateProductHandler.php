<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Product\UpdateProduct;

use App\Core\Catalog\Application\ReadModel\Projector\ProductProjector;
use App\Core\Catalog\Application\ReadModel\View\ProductView;
use App\Core\Catalog\Domain\Entity\Product;
use App\Core\Catalog\Domain\Exception\Product\ProductNotFoundException;
use App\Core\Catalog\Domain\Exception\Product\ProductSkuAlreadyExistsException;
use App\Core\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;

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
