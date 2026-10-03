<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Product\DeleteProductPermanently;

use App\Core\Catalog\Domain\Exception\Product\ProductNotDeletedException;
use App\Core\Catalog\Domain\Exception\Product\ProductNotFoundException;
use App\Core\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;

final readonly class DeleteProductPermanentlyHandler
{
    public function __construct(private ProductRepositoryInterface $products)
    {
    }

    /**
     * @throws ProductNotFoundException
     * @throws ProductNotDeletedException
     */
    public function __invoke(DeleteProductPermanentlyCommand $command): void
    {
        if (!$this->products->deletePermanently($command->id)) {
            throw new ProductNotFoundException('Product not found.');
        }
    }
}
