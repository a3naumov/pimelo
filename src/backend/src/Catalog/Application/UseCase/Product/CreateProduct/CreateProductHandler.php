<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Product\CreateProduct;

use App\Catalog\Application\ReadModel\Projector\ProductProjector;
use App\Catalog\Application\ReadModel\View\ProductView;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Exception\Product\ProductSkuAlreadyExistsException;
use App\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use App\General\Identity\IdGeneratorInterface;

final readonly class CreateProductHandler
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private IdGeneratorInterface $ids,
        private ProductProjector $projector,
    ) {
    }

    /**
     * @throws ProductSkuAlreadyExistsException
     */
    public function __invoke(CreateProductCommand $command): ProductView
    {
        return $this->projector->one($this->products->save(new Product($this->ids->generate(), $command->sku)));
    }
}
