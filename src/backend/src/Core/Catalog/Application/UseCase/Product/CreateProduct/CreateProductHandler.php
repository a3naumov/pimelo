<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Product\CreateProduct;

use App\Core\Catalog\Application\ReadModel\Projector\ProductProjector;
use App\Core\Catalog\Application\ReadModel\View\ProductView;
use App\Core\Catalog\Domain\Entity\Product;
use App\Core\Catalog\Domain\Exception\Product\ProductSkuAlreadyExistsException;
use App\Core\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use App\Shared\General\Identity\IdGeneratorInterface;

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
